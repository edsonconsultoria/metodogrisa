<?php
// app/modules/backup/service.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class BackupService
{
    private string $backupDir = '';
    private array $smtpConfigs = [
        ['port' => 25, 'secure' => false, 'autoTLS' => false],
        ['port' => 587, 'secure' => PHPMailer::ENCRYPTION_STARTTLS, 'autoTLS' => true],
        ['port' => 465, 'secure' => PHPMailer::ENCRYPTION_SMTPS, 'autoTLS' => false],
    ];
    private string $smtpHost = 'mail.icontrole.com.br';
    private string $smtpUser = 'contato@icontrole.com.br';
    private string $smtpPass = '@SG61[[%ZHv&';
    private string $recipient = 'edsonconsultoria@gmail.com';
    private string $senderName = 'Método Grisa Backup';
    private string $zipPassword = 'cirilo2508';
    private string $backupMetadataFile = '';

    public function __construct(?string $backupDir = null)
    {
        $this->backupDir = $backupDir ?? __DIR__ . '/../../storage/backups';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        $this->backupMetadataFile = $this->backupDir . '/backup.json';
        if (!is_file($this->backupMetadataFile)) {
            file_put_contents($this->backupMetadataFile, "[]");
        }
    }

    public function run(): string
    {
        $dumpPath = $this->createDump();
        $zipPath = $this->createZip($dumpPath);
        // Registra o backup assim que o ZIP é criado, para manter histórico em JSON
        $this->recordBackup($zipPath);
        try {
            $this->sendMail($zipPath);
            $this->cleanupFiles([$dumpPath, $zipPath]);
            return 'Backup gerado com sucesso, enviado por email (ZIP protegido) e registrado em backup.json.';
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Erro ao enviar o backup: ' . $e->getMessage()
                . ' | Arquivos mantidos em ' . $zipPath . ' e ' . $dumpPath,
                0,
                $e
            );
        }
    }

    private function createDump(): string
    {
        $timestamp = (new \DateTime())->format('Y-m-d-H-i-s');
        $filename = "metodo-grisa-{$timestamp}.sql";
        $filePath = $this->backupDir . '/' . $filename;

        $options = implode(' ', [
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--hex-blob',
            '--add-drop-database',
            '--add-drop-table',
            '--no-tablespaces',
        ]);
        $command = sprintf(
            'mysqldump %s -h %s -u %s -p%s --databases %s > %s',
            $options,
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($filePath)
        );
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Falha ao gerar dump: ' . implode("\n", $output));
        }
        return $filePath;
    }

    private function createZip(string $dumpPath): string
    {
        $zipFilename = pathinfo($dumpPath, PATHINFO_FILENAME) . '.zip';
        $zipPath = $this->backupDir . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Falha ao criar arquivo ZIP.');
        }
        $zip->setPassword($this->zipPassword);
        if (!$zip->addFile($dumpPath, basename($dumpPath))) {
            throw new \RuntimeException('Falha ao adicionar o dump no ZIP.');
        }
        if (!$zip->setEncryptionName(basename($dumpPath), ZipArchive::EM_AES_256)) {
            throw new \RuntimeException('Falha ao proteger o arquivo ZIP com senha.');
        }
        $zip->close();

        return $zipPath;
    }

    private function recordBackup(string $zipPath): void
    {
        $entry = [
            'filename' => basename($zipPath),
            'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'size' => is_file($zipPath) ? filesize($zipPath) : 0,
        ];
        $metadata = $this->loadBackupMetadata();
        array_unshift($metadata, $entry);
        $this->saveBackupMetadata($metadata);
    }

    private function loadBackupMetadata(): array
    {
        if (!is_file($this->backupMetadataFile)) {
            return [];
        }

        $content = file_get_contents($this->backupMetadataFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function saveBackupMetadata(array $data): void
    {
        file_put_contents(
            $this->backupMetadataFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function sendMail(string $zipPath): void
    {
        $lastError = '';
        foreach ($this->smtpConfigs as $config) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $this->smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $this->smtpUser;
                $mail->Password = $this->smtpPass;
                $mail->SMTPSecure = $config['secure'];
                $mail->SMTPAutoTLS = $config['autoTLS'];
                $mail->Port = $config['port'];
                $mail->setFrom($this->smtpUser, $this->senderName);
                $mail->addAddress($this->recipient);
                $mail->Subject = 'Backup Método Grisa ' . date('Y-m-d H:i:s');
                $mail->Body = 'Backup gerado em ' . date('d/m/Y H:i:s') . ' em anexo.';
                $mail->AltBody = 'Backup Método Grisa em anexo.';
                $mail->addAttachment($zipPath);
                $mail->send();
                return;
            } catch (PHPMailerException $mailException) {
                $lastError = 'porta ' . $config['port'] . ': ' . $mailException->getMessage();
            }
        }

        throw new \Exception('Não foi possível enviar o email: ' . $lastError);
    }

    private function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

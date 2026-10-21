<?php
// app/helpers/DocumentProcessor.php

class DocumentProcessor
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Processa as variáveis de um modelo para uma pessoa específica.
     */
    public function resolveVariables(int $modeloId, int $pessoaId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM 104_documentos_modelos_variaveis WHERE modelo_id = ? AND ativo = 1");
        $stmt->execute([$modeloId]);
        $vars = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resolved = [];
        foreach ($vars as $v) {
            $tagName = trim($v['variavel']);

            // Se a tag já tiver {{ }}, não adicionamos de novo
            if (strpos($tagName, '{{') === 0 && strpos($tagName, '}}') !== false) {
                $tag = $tagName;
            } else {
                $tag = '{{' . $tagName . '}}';
            }

            $value = '';

            switch ($v['origem']) {
                case 'fixo':
                    $value = $v['valor'];
                    break;
                case 'sql':
                    try {
                        $sqlQuery = $v['valor'];
                        $sqlStmt = $this->pdo->prepare($sqlQuery);

                        // Só passa o parâmetro se a query exigir :pessoa_id
                        if (strpos($sqlQuery, ':pessoa_id') !== false) {
                            $sqlStmt->execute(['pessoa_id' => $pessoaId]);
                        } else {
                            $sqlStmt->execute();
                        }

                        $value = $sqlStmt->fetchColumn() ?: '';
                    } catch (Exception $e) {
                        $value = "[Erro SQL: " . $e->getMessage() . "]";
                    }
                    break;
                case 'funcao':
                    $funcName = trim($v['valor']);
                    if ($funcName === 'data_extenso' || $funcName === 'date_atual_extenso') {
                        require_once __DIR__ . '/texto.php';
                        $value = data_extenso();
                    } else {
                        $value = "[Função " . $funcName . " não definida]";
                    }
                    break;
            }
            // Armazenamos a tag como ela está, mas usaremos uma lógica mais flexível na substituição
            $resolved[$tag] = $value;
        }

        return $resolved;
    }

    /**
     * Limpa a fragmentação de tags XML dentro de um conteúdo DOCX.
     * Word costuma dividir tags como {{nome}} em vários nós XML.
     */
    private function cleanXmlTags(string $content): string
    {
        // Word costuma dividir tags como {{nome}} em vários nós XML: {{n<w:rPr>...</w:rPr>ome}}
        // Removemos todas as tags XML de dentro do que parece ser uma chave {{...}} ou {...}
        // buscando de forma gananciosa o que começa com { e termina com }
        return preg_replace_callback('/\{+.*?\}+/su', function ($match) {
            // Removemos todas as tags XML de dentro (strip_tags)
            return strip_tags($match[0]);
        }, $content);
    }

    /**
     * Gera o arquivo .docx substituindo as tags no template.
     */
    public function generate(string $templatePath, string $outputPath, array $variables): bool
    {
        if (!file_exists($templatePath)) {
            error_log("DocumentProcessor: Template não encontrado em $templatePath");
            return false;
        }

        // Validação de extensão: somente .docx é suportado pelo processamento via ZipArchive
        $ext = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
        if ($ext !== 'docx') {
            error_log("DocumentProcessor: Formato inválido ($ext). O sistema suporta apenas arquivos .docx reais.");
            return false;
        }

        // Criar uma cópia do template como destino
        if (!copy($templatePath, $outputPath)) {
            error_log("DocumentProcessor: Falha ao copiar template para $outputPath");
            return false;
        }

        $zip = new ZipArchive();
        $res = $zip->open($outputPath);
        if ($res === true) {
            // Lista de arquivos XML que podem conter tags
            $xmlFiles = ['word/document.xml'];
            for ($i = 1; $i <= 10; $i++) {
                $xmlFiles[] = "word/header{$i}.xml";
                $xmlFiles[] = "word/footer{$i}.xml";
            }

            foreach ($xmlFiles as $xmlFile) {
                $content = $zip->getFromName($xmlFile);
                if ($content === false)
                    continue;

                // Limpar fragmentação antes da substituição
                $content = $this->cleanXmlTags($content);

                foreach ($variables as $tag => $val) {
                    // Substituição case-insensitive e tratando caracteres XML
                    $valEscaped = htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');

                    // Como limpamos o XML das tags, agora o str_replace literal deve funcionar
                    $content = str_replace($tag, $valEscaped, $content);

                    // Fallback case-insensitive se necessário
                    $content = str_ireplace($tag, $valEscaped, $content);
                }

                if (!$zip->addFromString($xmlFile, $content)) {
                    error_log("DocumentProcessor: Falha ao atualizar $xmlFile no ZIP.");
                }
            }

            return $zip->close();
        } else {
            error_log("DocumentProcessor: Erro ao abrir ZIP ($res). Certifique-se que o template é um .docx válido.");
            @unlink($outputPath); // Remove cópia corrompida se não for um zip válido
        }

        return false;
    }
}

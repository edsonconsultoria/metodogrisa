<?php
echo "Current User: " . get_current_user() . "\n";
echo "Script User (UID): " . getmyuid() . "\n";
echo "Effective User: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo "Files Dir Permissions: " . substr(sprintf('%o', fileperms(__DIR__ . '/../files/')), -4) . "\n";
echo "Is Files Writable: " . (is_writable(__DIR__ . '/../files/') ? 'Yes' : 'No') . "\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";

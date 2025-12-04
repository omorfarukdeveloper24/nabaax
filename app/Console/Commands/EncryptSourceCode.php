<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Filesystem\Filesystem;

class EncryptSourceCode extends Command
{
  protected $signature = 'source:encrypt {path}';
  protected $description = 'Encrypt source code files';
  protected $filesystem;

public function __construct(Filesystem $filesystem)
{
  parent::__construct();
  $this->filesystem = $filesystem;
}

public function handle()
{
  $path = $this->argument('path');
  if (!$this->filesystem->exists($path)) {
  $this->error('Path does not exist.');
  return 1;
}

$files = $this->filesystem->allFiles($path);
  foreach ($files as $file) {
   if ($file->getExtension() == 'php') {
    $this->encryptFile($file);
  }
}
 $this->info('Source code encrypted successfully.');
 return 0;
}

protected function encryptFile($file)
{
  $contents = $this->filesystem->get($file);
  $encrypted = Crypt::encryptString($contents);
  $encryptedContent = '<?php' . PHP_EOL . PHP_EOL . '// Encrypted' . PHP_EOL . PHP_EOL . base64_encode($encrypted);
  $this->filesystem->put($file, $encryptedContent);
  }
}
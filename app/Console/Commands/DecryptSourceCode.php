<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Filesystem\Filesystem;

class DecryptSourceCode extends Command
{
  protected $signature = 'source:decrypt {path}';
  protected $description = 'Decrypt source code files';
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
  $this->decryptFile($file);
  }
}

  $this->info('Source code decrypted successfully.');
  return 0;
}

protected function decryptFile($file)
{
  $contents = $this->filesystem->get($file);
  $encryptedContent = str_replace('<?php' . PHP_EOL . PHP_EOL . '// Encrypted' . PHP_EOL . PHP_EOL, '', $contents);
  $encryptedContent = base64_decode($encryptedContent);
  $decrypted = Crypt::decryptString($encryptedContent);
  $this->filesystem->put($file, $decrypted);
  }
}
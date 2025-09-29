<?php


namespace AZ\Laravel\Console;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrateDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:import';



    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import scripts from DFD. (Search scripts in ./database/model/script/xx/x.name.sql)';


    /**
     *
     * @var int|null
     */
    private $time = null;



    /**
     * 
     *
     * @return array
     */
    private function getDirs(): array
    {

        $home = \base_path() . '/database/model/script/';

        $result = collect(File::directories($home))
            ->map(function ($path) {
                return basename($path);
            })
            ->toArray();

        foreach ($result as &$dir) {

            $dir = (int)$dir;
        }

        unset($dir);

        \asort($result);

        foreach ($result as &$dir) {

            $dir = \realpath("{$home}$dir");
            $dir = $dir ? $dir : null;
        }

        unset($dir);

        $result = \array_filter($result, 'trim');
        $result = \array_values($result);

        return $result;
    }


    /**
     * 
     *
     * @return array
     */
    private function getFiles(): array
    {

        $supported = ['php', 'sql'];

        $dirs = $this->getDirs();
        $files = [];
        $result = [];

        foreach ($dirs as $dir_index => $dir) {


            $tmp = collect(File::files($dir))
                ->map(function ($path) {
                    return basename($path);
                })
                ->toArray();

            foreach ($tmp as $file) {


                $ext = \strtolower(\pathinfo($file, \PATHINFO_EXTENSION));

                if (!\in_array($ext, $supported)) {

                    continue;
                }


                $parse = \explode('.', $file, 2);

                if (count($parse) < 2) {

                    continue;
                }

                $index = (int)$parse[0];
                $name = $parse[1];

                $path = "{$dir}/{$index}.{$name}";

                if (!\file_exists($path)) {

                    $message = "Unsupported name format: {$file}";
                    echo "\033[31m{$message}\033[0m\n";
                    continue;
                }

                if (isset($files[$dir_index][$index])) {

                    $message = "Index already exists: {$file}";
                    echo "\033[31m{$message}\033[0m\n";
                    continue;
                }

                $files[$dir_index][$index] = $path;
            }
        }



        foreach ($files as $dir) {
            foreach ($dir as $file) {

                $result[] = $file;
            }
        }


        return $result;
    }




    /**
     * Convert absolute path to relative path
     *
     * @param string $absolutePath   The full absolute path
     * @return string                Relative path
     */
    function absoluteToRelative(string $absolutePath): string
    {

        $basePath = \base_path();

        // Normalize paths (remove trailing slashes)
        $absolutePath = rtrim(realpath($absolutePath), DIRECTORY_SEPARATOR);
        $basePath     = rtrim(realpath($basePath), DIRECTORY_SEPARATOR);

        // If absolute path does not start with base path, just return original
        if (strpos($absolutePath, $basePath) !== 0) {
            return $absolutePath;
        }

        // Cut the base path
        $relative = substr($absolutePath, strlen($basePath) + 1);

        return $relative ?: '.';
    }


    /**
     * 
     *
     * @return array
     */
    private function getExists(): array {

        $result = [];

        $files = collect(File::files(base_path() . '/database/migrations/'))
                ->map(function ($path) {
                    return basename($path);
                })
                ->toArray();

        // d($files);

        foreach ($files as $file) {

            $hash = \pathinfo($file, \PATHINFO_FILENAME);
            $hash = \explode('_', $hash);

            if (\count($hash) < 5) {

                continue;
            }

            $hash = $hash[4];

            if (\strlen($hash) <> 40) {

                continue;
            }

            $result[$hash] = $file;

        }


        return $result;
    }



    /**
     * Undocumented function
     *
     * @param string $file
     * @param string $template
     * @return void
     */
    private function procFile(string $file, string $template)
    {

        if ($this->time) {

            $this->time++;
        } else {

            $this->time = (int)date("His");
        }

        $exists = $this->getExists();

        // d($exists);

        $timestamp = date("Y_m_d") . '_' . $this->time;
        $hash = \explode('model/script/', $file);
        $hash = \end($hash);
        $hash = \sha1($hash);

        $migration =  \base_path() . "/database/migrations/{$timestamp}_{$hash}.php";

        $file = $this->absoluteToRelative($file);

        if (isset($exists[$hash])) {

            echo "{$file} -> {$migration}\n";
            return;
        }

        $message = "{$file} -> {$migration}"; 
        echo "\033[32m{$message}\033[0m\n";

        $contents = File::get($template);
        $contents = \str_replace('$filename', "\base_path() . '/{$file}'", $contents);
        \file_put_contents($migration, $contents);

        
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $files = $this->getFiles();

        foreach ($files as $file) {

            $ext = \strtolower(\pathinfo($file, \PATHINFO_EXTENSION));

            if ($ext == 'sql') {

                $this->procFile($file, \base_path() . '/vendor/az/laravel/template/migrations/sql.php');
                continue;
            }

            if ($ext == 'php') {

                $this->procFile($file, \base_path() . '/vendor/az/laravel/template/migrations/php.php');
                continue;                
            }

            $message = "Unsupported file extension: {$file}";
            echo "\033[31m{$message}\033[0m\n";
        }

        // d($files);


        return Command::SUCCESS;
    }
}

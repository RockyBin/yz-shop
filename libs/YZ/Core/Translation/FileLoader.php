<?php
//phpcodelock
namespace YZ\Core\Translation;

use RuntimeException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Translation\FileLoader as BaseFileLoader;
use YZ\Core\Logger\Log;

class FileLoader extends BaseFileLoader implements Loader
{
    public $default_path = '';
    public $loaded_files = [];

    public function __construct(Filesystem $files, $path, $default_path)
    {
        $this->path = $path;
        $this->files = $files;
        $this->default_path = $default_path;
    }

    /**
     * Load the messages for the given locale.
     * @param string $locale
     * @param string $group
     * @param null $namespace
     * @return array|mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function load($locale, $group, $namespace = null)
    {
        $file = $this->default_path . '/' . $locale . '/' . $group . '.json';
        // 加载系统默认配置
        if ($this->loaded_files[$file]) return $this->loaded_files[$file];
        if ($this->files->exists($file)) {
            $decoded = json_decode($this->files->get($file), true);
            if (!$decoded || json_last_error() !== JSON_ERROR_NONE) {
                Log::writeLog('translation_error', 'decoded main: ' . $file);
                throw new RuntimeException("Translation file [{$file}] contains an invalid JSON structure.");
            }
            // 加载私有配置
            $fileCustom = $this->path . '/' . $locale . '/' . $group . '.json';
            if (file_exists($fileCustom)) {
                $decodedCustom = json_decode($this->files->get($fileCustom), true);
                if ($decodedCustom && json_last_error() === JSON_ERROR_NONE) {
                    myArrayMerge($decoded, $decodedCustom);
                }
            }
            // 特殊处理
            if ($decoded['diy_word']) {
                pregReplaceForLang($decoded, $decoded['diy_word']);
            }
            if ($decoded) {
                $this->loaded_files[$file] = $decoded;
                return $decoded;
            } else {
                Log::writeLog('translation_error', 'decoded final: ' . $fileCustom);
            }
        } else {
            Log::writeLog('translation_error', 'file not exist: ' . $file);
        }
    }
}

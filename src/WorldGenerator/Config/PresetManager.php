<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

use WorldGenerator\WorldGenerator;

final class PresetManager{

    private string $path;

    public function __construct(WorldGenerator $plugin){
        $this->path = rtrim($plugin->getDataFolder(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . "WG_Presets"
            . DIRECTORY_SEPARATOR;

        if(!is_dir($this->path)){
            if(!mkdir($this->path, 0777, true) && !is_dir($this->path)){
                throw new \RuntimeException(
                    "Не удалось создать папку пресетов: " . $this->path
                );
            }
        }
    }

    public function save(string $worldName, Preset $preset): void{
        $file = $this->getFile($worldName);

        try{
            $json = json_encode(
                $preset->toArray(),
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        }catch(\JsonException $e){
            throw new \RuntimeException(
                "Не удалось сериализовать пресет мира: " . $worldName,
                0,
                $e
            );
        }

        if(file_put_contents($file, $json, LOCK_EX) === false){
            throw new \RuntimeException(
                "Не удалось сохранить пресет: " . $file
            );
        }
    }

    public function load(string $worldName): ?Preset{
        $file = $this->getFile($worldName);

        if(!is_file($file)){
            return null;
        }

        $json = file_get_contents($file);

        if($json === false){
            return null;
        }

        try{
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }catch(\JsonException){
            return null;
        }

        if(!is_array($data)){
            return null;
        }

        return Preset::fromArray($data);
    }

    // Получение списка сохранённых пресетов
    public function getList(): array{
        $list = [];

        foreach(glob($this->path . "*_preset.json") ?: [] as $file){
            $name = basename($file, "_preset.json");

            if($name !== ""){
                $list[] = $name;
            }
        }

        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    public function exists(string $worldName): bool{
        return is_file($this->getFile($worldName));
    }

    public function delete(string $worldName): bool{
        $file = $this->getFile($worldName);

        return is_file($file) && unlink($file);
    }

    private function getFile(string $worldName): string{
        return $this->path . $this->sanitizeName($worldName) . "_preset.json";
    }

    private function sanitizeName(string $name): string{
        return preg_replace('/[\\\\\/:*?"<>|]/', '_', $name) ?? $name;
    }
}
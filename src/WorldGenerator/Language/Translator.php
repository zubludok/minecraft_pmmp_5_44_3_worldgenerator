<?php

declare(strict_types=1);

namespace WorldGenerator\Language;

use WorldGenerator\WorldGenerator;

final class Translator{

    private string $language;

    // @var array<string, string>
    private array $translations = [];

    // @var array<string, string>
    private array $fallbackTranslations = [];

    public function __construct(
        private WorldGenerator $plugin,
        string $language
    ){
        $this->fallbackTranslations = $this->loadLanguage("ru");
        $this->setLanguage($language);
    }

    public function setLanguage(string $language): void{
        $language = strtolower($language);
        if(!in_array($language, ["ru", "en"], true)){
            $language = "ru";
        }
        $this->language = $language;
        $this->translations = $this->loadLanguage($language);
    }

    public function getLanguage(): string{return $this->language;}

    // @param array<string, string|int|float> $replace
    public function get(string $key, array $replace = []): string{
        $text = $this->translations[$key] ?? $this->fallbackTranslations[$key] ?? $key;

        foreach($replace as $name => $value){
            $text = str_replace("{" . $name . "}",(string) $value,$text);
        }

        return str_replace("\\n", "\n", $text);
    }

    // @return array<string, string>
    private function loadLanguage(string $language): array{
        $resource = $this->plugin->getResource("languages/" . $language . ".ini");
        if($resource === null){
            return [];
        }
        try{
			$contents = stream_get_contents($resource);
        }finally{
            fclose($resource);
        }
        if($contents === false){
            return [];
        }
        $parsed = parse_ini_string($contents, false, INI_SCANNER_RAW);
        if($parsed === false){
            $this->plugin->getLogger()->warning("Failed to load language: " . $language);
            return [];
        }
        $result = [];
        foreach($parsed as $key => $value){
            if(is_string($value)){
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
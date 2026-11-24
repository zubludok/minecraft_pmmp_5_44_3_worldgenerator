<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

final class PlayerSession{
    private Preset $preset;
    // Создание новой сессии
    public function __construct(){$this->preset=new Preset();}
    // Получить пресет
    public function getPreset(): Preset{return $this->preset;}
    // Установить пресет
    public function setPreset(Preset $preset): void{$this->preset=$preset;}
}
<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

use WorldGenerator\WorldGenerator;

final class ConfigManager{

    private WorldGenerator $plugin;
    private PresetManager $presetManager;
    private SessionManager $sessionManager;

    // Инициализация менеджеров конфигурации
    public function __construct(WorldGenerator $plugin){
        $this->plugin=$plugin;
        @mkdir($plugin->getDataFolder()."presets/");
        $this->presetManager=new PresetManager($plugin);
        $this->sessionManager=new SessionManager();
    }

    // Менеджер пресетов
    public function getPresetManager(): PresetManager{return $this->presetManager;}

    // Менеджер сессий
    public function getSessionManager(): SessionManager{return $this->sessionManager;}
}
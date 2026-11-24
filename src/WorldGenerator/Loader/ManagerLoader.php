<?php

declare(strict_types=1);

namespace WorldGenerator\Loader;

use WorldGenerator\WorldGenerator;
use WorldGenerator\Config\ConfigManager;
use WorldGenerator\Generator\WorldGeneratorManager;
use WorldGenerator\Pack\PackManager;
use WorldGenerator\UI\UIManager;

final class ManagerLoader{

    // Создание всех менеджеров
    public static function load(WorldGenerator $plugin): void{
        $plugin->setConfigManager(new ConfigManager($plugin));
        $plugin->setGeneratorManager(new WorldGeneratorManager($plugin));
        $plugin->setPackManager(new PackManager($plugin));
        $plugin->setUIManager(new UIManager($plugin));
    }
}
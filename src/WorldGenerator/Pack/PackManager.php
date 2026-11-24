<?php

declare(strict_types=1);

namespace WorldGenerator\Pack;

use WorldGenerator\WorldGenerator;

final class PackManager{

    private WorldGenerator $plugin;

    public function __construct(WorldGenerator $plugin){
        $this->plugin=$plugin;
    }
}
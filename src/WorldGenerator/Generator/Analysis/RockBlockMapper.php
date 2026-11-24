<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Analysis;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;

final class RockBlockMapper{

    /** @var array<string, Block> */
    private array $blocks = [];

    public function getBlock(string $rock): Block{
        $rock = strtoupper(trim($rock));

        if(isset($this->blocks[$rock])){
            return clone $this->blocks[$rock];
        }

        $block = match($rock){
            "STONE" => VanillaBlocks::STONE(),
            "GRANITE" => VanillaBlocks::GRANITE(),
            "DIORITE" => VanillaBlocks::DIORITE(),
            "ANDESITE" => VanillaBlocks::ANDESITE(),
            "BASALT" => VanillaBlocks::BASALT(),
            "TUFF" => VanillaBlocks::TUFF(),
            "DEEPSLATE" => VanillaBlocks::DEEPSLATE(),
            "CALCITE" => VanillaBlocks::CALCITE(),
            "SAND" => VanillaBlocks::SAND(),
            "GRAVEL" => VanillaBlocks::GRAVEL(),
            "CLAY" => VanillaBlocks::CLAY(),
            "DRIPSTONE" => VanillaBlocks::DRIPSTONE_BLOCK(),
            "SANDSTONE" => VanillaBlocks::SANDSTONE(),
            "RED_SANDSTONE" => VanillaBlocks::RED_SANDSTONE(),
            default => VanillaBlocks::STONE()
        };

        $this->blocks[$rock] = $block;

        return clone $block;
    }
}
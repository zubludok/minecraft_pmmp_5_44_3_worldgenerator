<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Generators;

use pocketmine\world\ChunkManager;
use pocketmine\world\generator\Generator;
use RuntimeException;
use WorldGenerator\Generator\Analysis\LandscapeAnalyzer;

final class LandscapeGenerator extends Generator{

    private LandscapeAnalyzer $analyzer;

    public function __construct(
        int $seed,
        string $options
    ){
        parent::__construct($seed, $options);
        $data = json_decode($options, true);
        if(!is_array($data)){
            throw new RuntimeException("Invalid LandscapeGenerator options");
        }
        $path = (string)($data["landscapePath"] ?? "");
        if($path === ""){
            throw new RuntimeException("Missing landscapePath in generator options");
        }
        $worldHeight = (int)($data["worldHeight"] ?? 320);
        $this->analyzer = new LandscapeAnalyzer($path,$worldHeight);
    }

    public function generateChunk(ChunkManager $world,int $chunkX,int $chunkZ): void{$this->analyzer->generateChunk($world,$chunkX,$chunkZ);}
    public function populateChunk(ChunkManager $world,int $chunkX,int $chunkZ): void{}
}
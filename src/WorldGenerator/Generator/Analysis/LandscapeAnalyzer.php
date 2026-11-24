<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Analysis;

use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use RuntimeException;

final class LandscapeAnalyzer{

    private int $sizeX;
    private int $sizeZ;
    private int $blockSizeX;
    private int $blockSizeZ;
    private int $worldHeight;
    private int $waterLevel;

    // @var array<string, list<array{rock:string, height:int}>>
    private array $columns = [];
    private RockBlockMapper $blockMapper;

    public function __construct(
		string $jsonPath,
        int $worldHeight
    ){
        if(!is_file($jsonPath)){
            throw new RuntimeException("Landscape file not found: " . $jsonPath);
        }
        $json = file_get_contents($jsonPath);
        if($json === false){
            throw new RuntimeException("Cannot read landscape file: " . $jsonPath);
        }
        $data = json_decode($json, true);
        if(!is_array($data)){
            throw new RuntimeException("Invalid landscape JSON: " . json_last_error_msg());
        }

        $this->sizeX = (int)($data["sizeX"] ?? 0);
        $this->sizeZ = (int)($data["sizeZ"] ?? 0);
        $this->worldHeight = max(1, $worldHeight);
        if($this->sizeX < 1 || $this->sizeZ < 1){
            throw new RuntimeException("Invalid landscape dimensions");
        }

        $this->blockSizeX = $this->sizeX * 48;
        $this->blockSizeZ = $this->sizeZ * 48;

        $this->waterLevel = (int)($data["waterLevel"] ?? 61);
        $this->waterLevel = max(0,min($this->waterLevel, $this->worldHeight - 1));
        $this->blockMapper = new RockBlockMapper();
        $cells = $data["cells"] ?? null;
        if(!is_array($cells)){
            throw new RuntimeException("Landscape does not contain cells");
        }

        foreach($cells as $cell){
            if(!is_array($cell)){
                continue;
            }
            $x = (int)($cell["x"] ?? -1);
            $z = (int)($cell["z"] ?? -1);
            if($x < 0 || $x >= $this->sizeX || $z < 0 || $z >= $this->sizeZ){
                continue;
            }
            $layers = $cell["state"]["layers"] ?? [];
            if(!is_array($layers)){
                continue;
            }
            $column = [];
            foreach($layers as $layer){
                if(!is_array($layer)){
                    continue;
                }
                $amount = (float)($layer["amount"] ?? 0);
                if(!is_finite($amount) || $amount <= 0){
                    continue;
                }
                $rock = strtoupper(
                    trim((string)($layer["rock"] ?? "STONE"))
                );
                $column[] = ["rock" => $rock,"height" => (int)ceil($amount)];
            }
            $this->columns[$x . ":" . $z] = $column;
        }
    }

    public function getSizeX(): int{return $this->blockSizeX;}
    public function getSizeZ(): int{return $this->blockSizeZ;}
    
	public function generateChunk(ChunkManager $world,int $chunkX,int $chunkZ): void{
        $startX = $chunkX * 16;
        $startZ = $chunkZ * 16;
        $airTypeId = VanillaBlocks::AIR()->getTypeId();
        $water = VanillaBlocks::WATER();
        for($localX = 0; $localX < 16; ++$localX){
            $worldX = $startX + $localX;
            if($worldX < 0 || $worldX >= $this->blockSizeX){
                continue;
            }
            $sectorX = intdiv($worldX, 48);
            for($localZ = 0; $localZ < 16; ++$localZ){
                $worldZ = $startZ + $localZ;
                if($worldZ < 0 || $worldZ >= $this->blockSizeZ){
                    continue;
                }
                $sectorZ = intdiv($worldZ, 48);
                $column = $this->columns[$sectorX . ":" . $sectorZ] ?? [];
                $y = 0;
                foreach($column as $layer){
                    $height = $layer["height"];
                    $endY = min($y + $height,$this->worldHeight);
                    if($layer["rock"] !== "EMPTY"){
                        $block = $this->blockMapper->getBlock($layer["rock"]);
                        for($blockY = $y; $blockY < $endY; ++$blockY){
                            $world->setBlockAt($worldX,$blockY,$worldZ,$block);
                        }
                    }
                    $y += $height;
                    if($y >= $this->worldHeight){
                        break;
                    }
                }
                // Вода заполняет только воздух и не заменяет блоки пород.
                for($waterY = 0;$waterY <= $this->waterLevel;++$waterY){
                    if($world->getBlockAt(
                            $worldX,
                            $waterY,
                            $worldZ
                        )->getTypeId() === $airTypeId
                    ){
                        $world->setBlockAt(
                            $worldX,
                            $waterY,
                            $worldZ,
                            $water
                        );
                    }
                }
            }
        }
    }
}
<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

final class WorldData{	// настройки пользователя.
	const BORDER_VOID = "void";
	const BORDER_BEDROCK = "bedrock";
	
    private string $name="NewWorld";
    private int $seed=3333;
    // private int $sizeX=128;
    // private int $sizeZ=128;
	private int $sectorCountX=10;
	private int $sectorCountZ=10;
    private int $worldHeight=320;
    private int $waterLevel=61;
	private int $aquiferLevel=5;
	private int $landPercent = 10;
	private string $borderType="void";
	private string $generationType="islands";
	private int $islandCount=2;
    private bool $ringWorld=false;
	

    // Название
    public function getName(): string{return $this->name;}
    public function setName(string $value): void{$this->name=$value;}

    // Seed
    public function getSeed(): int{return $this->seed;}
    public function setSeed(int $value): void{$this->seed=$value;}

    // Размер мира
	public function getSectorCountX(): int{return $this->sectorCountX;}
	public function setSectorCountX(int $count): void{$this->sectorCountX=max(1,$count);}
	public function getSectorCountZ(): int{return $this->sectorCountZ;}
	public function setSectorCountZ(int $count): void{$this->sectorCountZ=max(1,$count);}
		// Размер мира в секторах
		public function getSectorSize(): int{return 48;}
		// Размер мира в чанках
		public function getChunkCountX(): int{return $this->sectorCountX*3;}
		public function getChunkCountZ(): int{return $this->sectorCountZ*3;}
		// Размер мира в блоках
		public function getBlockSizeX(): int{return $this->getChunkCountX()*16;}
		public function getBlockSizeZ(): int{return $this->getChunkCountZ()*16;}

    // Высота
    public function getWorldHeight(): int{return $this->worldHeight;}
    public function setWorldHeight(int $value): void{$this->worldHeight=max(64,min(512,$value));}

	// Соотношение суши к воде
	public function getLandPercent(): int{return $this->landPercent;}
	public function setLandPercent(int $value): void{$this->landPercent=max(0,min(100,$value));}
	
    // Высота воды
    public function getWaterLevel(): int{return $this->waterLevel;}
	public function setWaterLevel(int $value): void{$this->waterLevel=max(16,min(100,$value));}

	// Толщина водяного слоя
	public function getAquiferLevel(): int{return $this->aquiferLevel;}
	public function setAquiferLevel(int $value): void{
		$this->aquiferLevel=max(1,min(10,$value));
	}
	
    // Граница
	public function getBorderType(): string{return $this->borderType;}
	public function setBorderType(string $value): void{$this->borderType=$value;}

	// Тип генерации
	public function getGenerationType(): string{return $this->generationType;}
	public function setGenerationType(string $value): void{$this->generationType=$value;}
	
	// Количество островов
	public function getIslandCount(): int{return $this->islandCount;}
	public function setIslandCount(int $count): void{$this->islandCount=max(1,$count);}
	
    // Кольцевой мир
    public function isRingWorld(): bool{return $this->ringWorld;}
    public function setRingWorld(bool $value): void{$this->ringWorld=$value;}
	
	
    public function toArray(): array{
        return[
            "name"=>$this->name,
            "seed"=>$this->seed,
            "sectorCountX"=>$this->sectorCountX,
			"sectorCountZ"=>$this->sectorCountZ,
            "worldHeight"=>$this->worldHeight,
            "waterLevel"=>$this->waterLevel,
			"aquiferLevel"=>$this->aquiferLevel,
			"landPercent"=>$this->landPercent,
            "borderType"=>$this->borderType,
            "generationType"=>$this->generationType,
			"islandCount"=>$this->islandCount,
            "ringWorld"=>$this->ringWorld
        ];
    }

	public static function fromArray(array $data): self{
		$world=new self();
		if(isset($data["name"]))$world->setName((string)$data["name"]);
		if(isset($data["seed"]))$world->setSeed((int)$data["seed"]);
		if(isset($data["sectorCountX"]))$world->setSectorCountX((int)$data["sectorCountX"]);
		if(isset($data["sectorCountZ"]))$world->setSectorCountZ((int)$data["sectorCountZ"]);
		if(isset($data["worldHeight"]))$world->setWorldHeight((int)$data["worldHeight"]);
		if(isset($data["waterLevel"]))$world->setWaterLevel((int)$data["waterLevel"]);
		if(isset($data["aquiferLevel"]))$world->setAquiferLevel((int)$data["aquiferLevel"]);
		if(isset($data["landPercent"])){$world->setLandPercent((int)$data["landPercent"]);}
		if(isset($data["borderType"]))$world->setBorderType((string)$data["borderType"]);
		if(isset($data["generationType"]))$world->setGenerationType((string)$data["generationType"]);
		if(isset($data["islandCount"]))$world->setIslandCount((int)$data["islandCount"]);
		if(isset($data["ringWorld"]))$world->setRingWorld((bool)$data["ringWorld"]);
		return $world;
	}
}
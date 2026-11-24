<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

final class Preset{

    private WorldData $world;
    private TerrainData $terrain;
    private BiomeData $biome;
    private FloraData $flora;
    private FaunaData $fauna;
    private ResourceData $resource;
    private PackData $pack;
    private GeneratorData $generator;
	private int $polarShift = 0;

    public function __construct(){
        $this->world=new WorldData();
        $this->terrain=new TerrainData();
        $this->biome=new BiomeData();
        $this->flora=new FloraData();
        $this->fauna=new FaunaData();
        $this->resource=new ResourceData();
        $this->pack=new PackData();
        $this->generator=new GeneratorData();
    }

    public function getWorld(): WorldData{return $this->world;}
    public function getTerrain(): TerrainData{return $this->terrain;}
    public function getBiome(): BiomeData{return $this->biome;}
    public function getFlora(): FloraData{return $this->flora;}
    public function getFauna(): FaunaData{return $this->fauna;}
    public function getResource(): ResourceData{return $this->resource;}
    public function getPack(): PackData{return $this->pack;}
    public function getGenerator(): GeneratorData{return $this->generator;}
	public function getPolarShift(): int{return $this->polarShift;}
	public function setPolarShift(int $polarShift): void{$this->polarShift = $polarShift;}
	public function getWorldHeight(): int{return $this->world->getWorldHeight();}
	public function setWorldHeight(int $value): void{$this->world->setWorldHeight($value);}
	public function getAquiferLevel(): int{return $this->world->getAquiferLevel();}
	public function setAquiferLevel(int $value): void{$this->world->setAquiferLevel($value);}
	public function getBorderType(): string{return $this->world->getBorderType();}
	public function setBorderType(string $value): void{$this->world->setBorderType($value);}
	public function getGenerationType(): string{return $this->world->getGenerationType();}
	public function setGenerationType(string $value): void{$this->world->setGenerationType($value);}
	public function toArray(): array{
		return [
			"world" => $this->world->toArray(),
			"terrain" => $this->terrain->toArray(),
			"biome" => $this->biome->toArray(),
			"flora" => $this->flora->toArray(),
			"fauna" => $this->fauna->toArray(),
			"resource" => $this->resource->toArray(),
			"pack" => $this->pack->toArray(),
			"generator" => $this->generator->toArray(),
			"polarShift" => $this->polarShift,
		];
	}

	public static function fromArray(array $data): self{
		$preset=new self();
		if(isset($data["world"]))$preset->world=WorldData::fromArray($data["world"]);
		if(isset($data["terrain"]))$preset->terrain=TerrainData::fromArray($data["terrain"]);
		if(isset($data["biome"]))$preset->biome=BiomeData::fromArray($data["biome"]);
		if(isset($data["flora"]))$preset->flora=FloraData::fromArray($data["flora"]);
		if(isset($data["fauna"]))$preset->fauna=FaunaData::fromArray($data["fauna"]);
		if(isset($data["resource"]))$preset->resource=ResourceData::fromArray($data["resource"]);
		if(isset($data["pack"]))$preset->pack=PackData::fromArray($data["pack"]);
		if(isset($data["generator"]))$preset->generator=GeneratorData::fromArray($data["generator"]);
		if(isset($data["polarShift"])){$preset->setPolarShift((int) $data["polarShift"]);}
		return $preset;
	}
}
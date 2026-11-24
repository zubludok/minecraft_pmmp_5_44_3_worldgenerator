<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

final class TerrainData{

    // Тип генерации
    private string $type="flat";

    // Соотношение суши
    private int $landPercent=50;

    // Количество островов
    private int $islandCount=1;

    // Дополнительные объекты
    private bool $mountains=true;
    private bool $rivers=true;
    private bool $canyons=true;
    private bool $caves=true;
    private bool $reefs=true;

    // ---------- Тип ----------

    public function getType(): string{return $this->type;}
    public function setType(string $value): void{$this->type=$value;}

    // ---------- Суша ----------

    public function getLandPercent(): int{return $this->landPercent;}
    public function setLandPercent(int $value): void{$this->landPercent=max(0,min(100,$value));}

    // ---------- Острова ----------

    public function getIslandCount(): int{return $this->islandCount;}
    public function setIslandCount(int $value): void{$this->islandCount=max(1,$value);}

    // ---------- Горы ----------

    public function hasMountains(): bool{return $this->mountains;}
    public function setMountains(bool $value): void{$this->mountains=$value;}

    // ---------- Реки ----------

    public function hasRivers(): bool{return $this->rivers;}
    public function setRivers(bool $value): void{$this->rivers=$value;}

    // ---------- Ущелья ----------

    public function hasCanyons(): bool{return $this->canyons;}
    public function setCanyons(bool $value): void{$this->canyons=$value;}

    // ---------- Пещеры ----------

    public function hasCaves(): bool{return $this->caves;}
    public function setCaves(bool $value): void{$this->caves=$value;}

    // ---------- Рифы ----------

    public function hasReefs(): bool{return $this->reefs;}
    public function setReefs(bool $value): void{$this->reefs=$value;}

    // ---------- Сохранение ----------

    public function toArray(): array{
        return[
            "type"=>$this->type,
            "landPercent"=>$this->landPercent,
            "islandCount"=>$this->islandCount,
            "mountains"=>$this->mountains,
            "rivers"=>$this->rivers,
            "canyons"=>$this->canyons,
            "caves"=>$this->caves,
            "reefs"=>$this->reefs
        ];
    }

    public static function fromArray(array $data): self{
        $terrain=new self();
        if(isset($data["type"]))$terrain->setType((string)$data["type"]);
        if(isset($data["landPercent"]))$terrain->setLandPercent((int)$data["landPercent"]);
        if(isset($data["islandCount"]))$terrain->setIslandCount((int)$data["islandCount"]);
        if(isset($data["mountains"]))$terrain->setMountains((bool)$data["mountains"]);
        if(isset($data["rivers"]))$terrain->setRivers((bool)$data["rivers"]);
        if(isset($data["canyons"]))$terrain->setCanyons((bool)$data["canyons"]);
        if(isset($data["caves"]))$terrain->setCaves((bool)$data["caves"]);
        if(isset($data["reefs"]))$terrain->setReefs((bool)$data["reefs"]);
        return $terrain;
    }
}
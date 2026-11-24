<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Formation;

final class FormationEvent{

    public const COLLISION="collision";
    public const FRACTURE="fracture";
    public const MIXING="mixing";
    public const REDISTRIBUTION="redistribution";
    public const DEPOSITION="deposition";

    private int $step;
    private string $type;
    /** @var int[] */
    private array $participants;
    private int $x;
    private int $z;
    private int $pressure;
    private int $directionX;
    private int $directionZ;
    private int $intensity;
    private FormationSnapshot $before;
    private FormationSnapshot $after;
    private int $deformation;

    /**
     * Создаёт историческую запись изменения клетки.
     *
     * @param int[] $participants
     */
    public function __construct(
        int $step,
        string $type,
        array $participants,
        int $x,
        int $z,
        int $pressure,
        int $directionX,
        int $directionZ,
        int $intensity,
        FormationSnapshot $before,
        FormationSnapshot $after,
        int $deformation
    ){
        $this->step=$step;
        $this->type=$type;
        $this->participants=array_values($participants);
        $this->x=$x;
        $this->z=$z;
        $this->pressure=max(0,$pressure);
        $this->directionX=$directionX;
        $this->directionZ=$directionZ;
        $this->intensity=max(0,$intensity);
        $this->before=$before;
        $this->after=$after;
        $this->deformation=max(0,$deformation);
    }

    public function getStep(): int{return $this->step;}
    public function getType(): string{return $this->type;}
    /** @return int[] */
    public function getParticipants(): array{return $this->participants;}
    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getPressure(): int{return $this->pressure;}
    public function getDirectionX(): int{return $this->directionX;}
    public function getDirectionZ(): int{return $this->directionZ;}
    public function getIntensity(): int{return $this->intensity;}
    public function getBefore(): FormationSnapshot{return $this->before;}
    public function getAfter(): FormationSnapshot{return $this->after;}
    public function getDeformation(): int{return $this->deformation;}
}
<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

interface IncrementalStage{

    public function getName(): string;
    /* Выполняет ограниченную порцию работы.
     * @param int $budget Максимальное количество единиц работы за вызов.
     * @return bool true, если этап полностью завершён. */
    public function advance(int $budget): bool;
    /* Прогресс от 0.0 до 1.0. */
    public function getProgress(): float;
}
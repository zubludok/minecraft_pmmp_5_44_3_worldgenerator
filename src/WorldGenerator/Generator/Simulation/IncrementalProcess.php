<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

interface IncrementalProcess{

    /* Выполняет ограниченную порцию вычислений.
     * @param int $budget Максимальное количество операций обработки.
     * @return bool true, если процесс полностью завершён. */
    public function advance(int $budget): bool;
    // Возвращает прогресс от 0.0 до 1.0.
    public function getProgress(): float;
    // Название текущего процесса для окна-заглушки.
    public function getStageName(): string;
    // Сообщение о текущей подоперации.
    public function getStatus(): string;
}
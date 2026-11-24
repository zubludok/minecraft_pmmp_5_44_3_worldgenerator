<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Mass\MassCell;

interface RockTransformationProcess{

    /**
     * Применяет физический процесс к содержимому ячейки.
     *
     * @return bool Было ли произведено изменение.
     */
    public function apply(MassCell $cell): bool;
}
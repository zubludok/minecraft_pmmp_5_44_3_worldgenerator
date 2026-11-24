<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Mass;

use Generator;
use Random\Engine\Mt19937;
use Random\Randomizer;
use WorldGenerator\Generator\Objects\ObjectLayout;
use WorldGenerator\Generator\Formation\Force;
use WorldGenerator\Generator\Simulation\IncrementalProcess;

final class Fragmenter implements IncrementalProcess{

    private const TRANSFER_PASSES = 1;

    private const PHASE_COLLECT = 0;
    private const PHASE_SEEDS = 1;
    private const PHASE_ASSIGN = 2;
    private const PHASE_BALANCE = 3;
    private const PHASE_TRANSFER = 4;
    private const PHASE_CLEANUP = 5;
    private const PHASE_COMPLETE = 6;

    private MassMap $map;
    private int $fragmentCount;
    private Randomizer $randomizer;
    private ObjectLayout $objectLayout;
    private Force $force;
    private array $fragments = [];	// @var Fragment[]
    private array $cells = [];		// @var MassCell[]
    private array $seeds = [];		// @var array<int, array{x:int,z:int}>
    private array $assigned = [];	// @var array<string,int>
    private int $phase = self::PHASE_COLLECT;
    private string $status = 'Подготовка';
    private ?Generator $process = null;
    private bool $finished = false;
    private array $result = [];		// @var Fragment[]
    private int $scanX = 0;
    private int $scanZ = 0;
    private int $objectIndex = 0;
    private int $cellIndex = 0;
    private int $seedIndex = 0;
    private int $receiverId = 0;
    private int $donorId = 0;
    private bool $balanceChanged = false;
    private bool $balancePassChanged = false;
    private int $transferPass = 0;
    private int $transferSender = 0;
    private int $transferAmount = 0;
    private int $transferIndex = 0;
    private int $maxTransfer = 1;
    private int $minTransfer = 1;
    private int $totalCells = 0;
    private int $processedCells = 0;
    private int $fragmentLimit = 0;

    public function __construct(
        MassMap $map,
        int $fragmentCount,
        int $seed,
        ObjectLayout $objectLayout,
        Force $force
    ){
        $this->map = $map;
        $this->fragmentCount = $fragmentCount;
        $this->randomizer = new Randomizer(new Mt19937($seed));
        $this->objectLayout = $objectLayout;
        $this->force = $force;
    }

    /* Совместимость со старым API.
     * ВАЖНО: этот метод выполняет весь процесс синхронно.
     * Для работы по тикам используй advance().
     * @return Fragment[] */
    public function generate(): array{	// Синхронный режим для совместимости со старым кодом.
        while(!$this->advance(1000)){}
        return $this->result;
    }

    // Выполняет не более указанного количества шагов автомата.
    public function advance(int $budget): bool{
        if($this->finished){return true;}
        if($budget <= 0){return false;}
        $this->process ??= $this->runProcess();
        for($i = 0; $i < $budget; ++$i){
            if(!$this->process->valid()){
                $this->finished = true;
                $this->result = $this->process->getReturn();
                $this->status = 'Завершено';
                $this->phase = self::PHASE_COMPLETE;
                return true;
            }
            $this->process->next();
        }
        if(!$this->process->valid()){
            $this->finished = true;
            $this->result = $this->process->getReturn();
            $this->status = 'Завершено';
            $this->phase = self::PHASE_COMPLETE;
        }
        return $this->finished;
    }

    public function getProgress(): float{
        if($this->finished){return 1.0;}
        // Прогресс по фазам. Внутри тяжёлых фаз он приближённый.
        $phaseProgress = match($this->phase){
            self::PHASE_COLLECT => $this->map->getSizeX() > 0 ? $this->scanX / $this->map->getSizeX() : 0.0,
            self::PHASE_SEEDS => $this->fragmentCount > 0 ? min(1.0, count($this->seeds) / $this->fragmentCount) : 1.0,
            self::PHASE_ASSIGN => $this->totalCells > 0 ? min(1.0, $this->processedCells / $this->totalCells): 0.0,
            self::PHASE_BALANCE => 0.5,
            self::PHASE_TRANSFER => 0.75,
            self::PHASE_CLEANUP => 0.95,
            default => 0.0
        };
        return min(0.99, max(0.0, ($this->phase + $phaseProgress) / 7.0));
    }

    public function getStageName(): string{return 'Формирование фрагментов';}
    public function getStatus(): string{return $this->status;}

    // @return Generator<int, null, mixed, array<int, Fragment>>
    private function runProcess(): Generator{
        $this->phase = self::PHASE_COLLECT;
        $this->status = 'Сбор клеток массы';
        $this->cells = yield from $this->collectMassCells();
        if($this->cells === [] || $this->fragmentCount <= 0){return [];}
        $this->totalCells = count($this->cells);
        $this->fragmentLimit = min($this->fragmentCount, $this->totalCells);
        if($this->fragmentLimit <= 0){return [];}
        $this->phase = self::PHASE_SEEDS;
        $this->status = 'Поиск начальных точек';
        $this->seeds = yield from $this->collectSeeds($this->fragmentLimit);
        if($this->seeds === []){return [];}
        $this->fragmentLimit = count($this->seeds);
        foreach($this->seeds as $id => $_seed){
            $this->fragments[$id] = new Fragment($id);
            yield null;
        }
        $this->phase = self::PHASE_ASSIGN;
        $this->status = 'Распределение клеток';
        yield from $this->assignCells();
        $this->logFragmentSizes('INITIAL');
        $this->phase = self::PHASE_BALANCE;
        $this->status = 'Балансировка размеров фрагментов';
        yield from $this->balanceFragments();
        $this->logFragmentSizes('BALANCED');
        $this->phase = self::PHASE_TRANSFER;
        $this->status = 'Передача клеток между фрагментами';
        yield from $this->transferCells();
        $this->phase = self::PHASE_CLEANUP;
        $this->status = 'Очистка пустых фрагментов';
        foreach($this->fragments as $id => $fragment){
            if($fragment->getSize() <= 0){
                unset($this->fragments[$id]);
            }
            yield null;
        }
        return array_values($this->fragments);
    }

    // @return Generator<int, null, mixed, MassCell[]>
    private function collectMassCells(): Generator{
        $cells = [];
        for($x = 0; $x < $this->map->getSizeX(); ++$x){
            $this->scanX = $x;
            for($z = 0; $z < $this->map->getSizeZ(); ++$z){
                $this->scanZ = $z;
                if($this->force->containsMapCell($x, $z, $this->map)){
                    $cell = $this->map->get($x, $z);
                    if($cell !== null && !$cell->isEmpty()){
                        $cells[] = $cell;
                    }
                }
                yield null;
            }
        }
        return $cells;
    }

    // @return Generator<int, null, mixed, array<int, array{x:int,z:int}>>
    private function collectSeeds(int $fragmentCount): Generator{
        $seeds = [];
        $used = [];
        $objects = $this->objectLayout->getObjects();
        foreach($objects as $object){
            $x = $object->getPositionX();
            $z = $object->getPositionZ();
            if($this->force->containsMapCell($x, $z, $this->map) && ($cell = $this->map->get($x, $z)) !== null && !$cell->isEmpty()){
                $key = $x . ':' . $z;
                if(!isset($used[$key])){
                    $seeds[] = ['x' => $x, 'z' => $z];
                    $used[$key] = true;
                }
            }
            yield null;
            if(count($seeds) >= $fragmentCount){return $seeds;}
        }
        $candidates = [];
        foreach($this->cells as $cell){
            $key = $cell->getX() . ':' . $cell->getZ();
            if(!isset($used[$key])){
                $candidates[] = $cell;
            }
            yield null;
        }
        while(count($seeds) < $fragmentCount && $candidates !== []){
            $index = $this->randomizer->getInt(0, count($candidates) - 1);
            $cell = $candidates[$index];
            unset($candidates[$index]);
            $candidates = array_values($candidates);
            $seeds[] = ['x' => $cell->getX(),'z' => $cell->getZ()];
            yield null;
        }
        return $seeds;
    }

    // @return Generator<int, null, mixed, null>
    private function assignCells(): Generator{
        $this->processedCells = 0;
        foreach($this->cells as $cell){
            $bestId = null;
            $bestDistance = PHP_INT_MAX;
            foreach($this->seeds as $id => $seed){
                $distance = abs($cell->getX() - $seed['x']) + abs($cell->getZ() - $seed['z']);
                if($distance < $bestDistance){
                    $bestDistance = $distance;
                    $bestId = $id;
                }
                yield null;
            }
            if($bestId !== null){
                $key = $cell->getX() . ':' . $cell->getZ();
                $this->assigned[$key] = $bestId;
            }
            ++$this->processedCells;
            yield null;
        }
        foreach($this->cells as $cell){
            $key = $cell->getX() . ':' . $cell->getZ();
            if(isset($this->assigned[$key])){
                $id = $this->assigned[$key];
                $this->fragments[$id]->addCell($cell);
            }
            yield null;
        }
    }

    // @return Generator<int, null, mixed, null>
    private function balanceFragments(): Generator{
        $total = 0;
        foreach($this->fragments as $fragment){
            $total += $fragment->getSize();
            yield null;
        }
        $count = count($this->fragments);
        if($count <= 0){
            return;
        }
        $base = intdiv($total, $count);
        $remainder = $total % $count;
        $targets = [];
        for($id = 0; $id < $count; ++$id){
            $targets[$id] = $base + ($id < $remainder ? 1 : 0);
            yield null;
        }
        do{
            $this->balancePassChanged = false;
            for($receiverId = 0; $receiverId < $count; ++$receiverId){
                if(!isset($this->fragments[$receiverId]) || $this->fragments[$receiverId]->getSize() >= $targets[$receiverId]){
                    yield null;
                    continue;
                }
                for($donorId = 0; $donorId < $count; ++$donorId){
                    if($donorId === $receiverId){
                        yield null;
                        continue;
                    }
                    if(!isset($this->fragments[$donorId]) || $this->fragments[$donorId]->getSize() <= $targets[$donorId]){
                        yield null;
                        continue;
                    }
                    $cell = yield from $this->findTransferCell($donorId, $receiverId);
                    if($cell !== null){
                        $this->moveCell($cell, $donorId, $receiverId);
                        $this->balancePassChanged = true;
                        yield null;
                        break;
                    }
                }
            }
            yield null;
        }while($this->balancePassChanged);
    }

    // @return Generator<int, null, mixed, null>
    private function transferCells(): Generator{
        $count = count($this->fragments);
        if($count <= 0){return;}
        $total = 0;
        foreach($this->fragments as $fragment){
            $total += $fragment->getSize();
            yield null;
        }
        $this->maxTransfer = max(1, intdiv(max(1, intdiv($total, $count)), $count));
        $this->minTransfer = max(1, (int)ceil($this->maxTransfer / $count));
        $this->minTransfer = min($this->minTransfer, $this->maxTransfer);
        for($pass = 0; $pass < self::TRANSFER_PASSES; ++$pass){
            for($sender = 0; $sender < $count; ++$sender){
                $receiver = ($sender + 1) % $count;
                if(!isset($this->fragments[$sender], $this->fragments[$receiver])){
                    yield null;
                    continue;
                }
                $senderSize = $this->fragments[$sender]->getSize();
                if($senderSize <= 1){
                    yield null;
                    continue;
                }
                $transferMax = min($this->maxTransfer, $senderSize - 1);
                $transferMin = min($this->minTransfer, $transferMax);
                if($transferMax <= 0){
                    yield null;
                    continue;
                }
                $amount = $this->randomizer->getInt($transferMin, $transferMax);
                for($i = 0; $i < $amount; ++$i){
                    $cell = yield from $this->findTransferCell($sender, $receiver);
                    if($cell === null){
                        break;
                    }
                    $this->moveCell($cell, $sender, $receiver);
                    yield null;
                }
            }
        }
    }

    /* Поиск кандидата разбит на отдельные операции.
     * @return Generator<int, null, mixed, ?MassCell> */
    private function findTransferCell(int $donorId, int $receiverId): Generator{
        if(!isset($this->fragments[$donorId])){return null;}
        $candidates = [];
        foreach($this->fragments[$donorId]->getCells() as $cell){
            if(!$this->isBoundaryCell($cell, $receiverId)){
                yield null;
                continue;
            }
            if(yield from $this->canRemoveCell($cell, $donorId)){
                $candidates[] = $cell;
            }
            yield null;
        }
        if($candidates === []){return null;}
        return $candidates[$this->randomizer->getInt(0, count($candidates) - 1)];
    }

    private function isBoundaryCell(MassCell $cell, int $receiverId): bool{
        foreach($this->getNeighbors($cell) as $neighbor){
            if($neighbor->getFragmentId() === $receiverId){return true;}
        }
        return false;
    }

    /* Проверка связности фрагмента посредством пошагового BFS.
     * @return Generator<int, null, mixed, bool> */
    private function canRemoveCell(MassCell $cell, int $fragmentId): Generator{
        $fragment = $this->fragments[$fragmentId] ?? null;
        if($fragment === null){return false;}
        if($fragment->getSize() <= 1){return true;}
        $remaining = [];
        foreach($fragment->getCells() as $fragmentCell){
            if($fragmentCell !== $cell){
                $remaining[] = $fragmentCell;
            }
            yield null;
        }
        if($remaining === []){return true;}
        $visited = [];
        $queue = [$remaining[0]];
        $head = 0;
        while($head < count($queue)){
            $current = $queue[$head++];
            $key = $current->getX() . ':' . $current->getZ();
            if(isset($visited[$key])){
                yield null;
                continue;
            }
            $visited[$key] = true;
            foreach($this->getNeighbors($current) as $neighbor){
                if($neighbor->getFragmentId() !== $fragmentId){
                    yield null;
                    continue;
                }
                $neighborKey = $neighbor->getX() . ':' . $neighbor->getZ();
                if(!isset($visited[$neighborKey])){
                    $queue[] = $neighbor;
                }
                yield null;
            }
            yield null;
        }
        return count($visited) === count($remaining);
    }

    private function moveCell(MassCell $cell, int $fromId, int $toId): void{
        if(!isset($this->fragments[$fromId], $this->fragments[$toId])){
            return;
        }
        $this->fragments[$fromId]->removeCell($cell);
        $this->fragments[$toId]->addCell($cell);
    }

    // @return MassCell[]
    private function getNeighbors(MassCell $cell): array{
        $neighbors = [];
        $positions = [
            [$cell->getX() - 1, $cell->getZ()],
            [$cell->getX() + 1, $cell->getZ()],
            [$cell->getX(), $cell->getZ() - 1],
            [$cell->getX(), $cell->getZ() + 1]
        ];
        foreach($positions as [$x, $z]){
            $neighbor = $this->map->get($x, $z);
            if($neighbor === null || $neighbor->isEmpty()){
                continue;
            }
            $neighbors[] = $neighbor;
        }
        return $neighbors;
    }

    private function logFragmentSizes(string $stage): void{
        echo '[Fragmenter][' . $stage . '] ';
        foreach($this->fragments as $id => $fragment){
            echo '#' . $id . '=' . $fragment->getSize() . ' ';
        }
        echo PHP_EOL;
    }

    /* Результат доступен после завершения advance().
     * @return Fragment[] */
    public function getResult(): array{return $this->finished ? $this->result : [];}
    public function isFinished(): bool{return $this->finished;}
}
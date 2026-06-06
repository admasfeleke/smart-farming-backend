<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;

class NormalizeRegionHierarchy extends Command
{
    protected $signature = 'regions:normalize-hierarchy {--apply : Persist fixes}';

    protected $description = 'Audit and normalize region hierarchy to region -> zone -> woreda -> kebele.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = Region::query()->with('parent')->orderBy('id')->get();
        $changes = 0;

        foreach ($rows as $region) {
            $originalLevel = $region->level;
            $originalParent = $region->parent_id;

            if ($region->parent_id === null) {
                if ($region->level !== Region::LEVEL_REGION) {
                    $region->level = Region::LEVEL_REGION;
                }
            } else {
                $parent = $region->parent;
                if (! $parent) {
                    $region->parent_id = null;
                    $region->level = Region::LEVEL_REGION;
                } else {
                    $expected = Region::expectedChildLevel($parent->level);
                    if ($expected !== null && $region->level !== $expected) {
                        $region->level = $expected;
                    }
                }
            }

            if ($region->level !== $originalLevel || $region->parent_id !== $originalParent) {
                $changes++;
                $this->line(sprintf(
                    '[fix] #%d %s | level: %s -> %s | parent: %s -> %s',
                    $region->id,
                    $region->name,
                    $originalLevel ?? 'null',
                    $region->level ?? 'null',
                    $originalParent ?? 'null',
                    $region->parent_id ?? 'null',
                ));

                if ($apply) {
                    $region->save();
                }
            }
        }

        if ($changes === 0) {
            $this->info('Hierarchy already valid.');
            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn("Detected {$changes} fix(es). Re-run with --apply to persist.");
            return self::SUCCESS;
        }

        $this->info("Applied {$changes} hierarchy fix(es).");
        return self::SUCCESS;
    }
}


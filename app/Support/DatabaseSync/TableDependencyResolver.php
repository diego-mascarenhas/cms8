<?php

namespace App\Support\DatabaseSync;

class TableDependencyResolver
{
    /**
     * @param  array<string, array<int, string>>  $dependencies
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    public static function resolve(array $dependencies, array $tables): array
    {
        $uniqueTables = array_values(array_unique($tables));
        $tablesLookup = array_fill_keys($uniqueTables, true);
        $normalizedDependencies = [];
        $inDegree = [];

        foreach ($uniqueTables as $table)
        {
            $inDegree[$table] = 0;
            $normalizedDependencies[$table] = [];
        }

        foreach ($dependencies as $table => $parents)
        {
            if (! isset($tablesLookup[$table]))
            {
                continue;
            }

            foreach ($parents as $parent)
            {
                if (! isset($tablesLookup[$parent]) || $parent === $table)
                {
                    continue;
                }

                if (in_array($parent, $normalizedDependencies[$table], true))
                {
                    continue;
                }

                $normalizedDependencies[$table][] = $parent;
                $inDegree[$table]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $table => $degree)
        {
            if ($degree === 0)
            {
                $queue[] = $table;
            }
        }

        sort($queue);
        $ordered = [];

        while (! empty($queue))
        {
            $current = array_shift($queue);
            $ordered[] = $current;

            foreach ($normalizedDependencies as $table => $parents)
            {
                if (! in_array($current, $parents, true))
                {
                    continue;
                }

                $inDegree[$table]--;
                if ($inDegree[$table] === 0)
                {
                    $queue[] = $table;
                    sort($queue);
                }
            }
        }

        if (count($ordered) !== count($uniqueTables))
        {
            $remaining = array_values(array_diff($uniqueTables, $ordered));
            sort($remaining);
            $ordered = array_merge($ordered, $remaining);
        }

        return $ordered;
    }
}

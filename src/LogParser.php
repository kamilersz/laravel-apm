<?php namespace Done\LaravelAPM;

class LogParser
{
    /**
     * @param string $type     What type of report: request, schedule, queue
     * @param string $group    By which property to group: total time, longest request...
     * @return array
     */
    public static function parse($type, $group)
    {
        $top_requests = [];
        $count_by_hour = [];
        $top_total_count = 0;
        $tmp_longest_i = 0;
        for ($i = 0; $i < 24; $i++) {
            $count_by_hour[$i . 'h'] = 0;
        }

        for ($i = 0; $i < 24; $i++) {
            $path = self::path($i);

            if (!\File::exists($path)) {
                continue;
            }

            $data = \File::get($path);
            $data = trim($data);

            $pattern = '/^\|([^\s|]+) ([^\s]+) ([^\s]+) ([^\s]+) ([^\s]+) ([^\s]+)\|$/m';
            preg_match_all($pattern, $data, $matches, PREG_SET_ORDER);
            foreach ($matches as $record) {
                // $timestamp, $duration, $sql_duration, $type, $name, $ip

                // filter by request type
                if ($record[4] !== $type) {
                    continue;
                }

                $hour = ($record[1] / 3600) % 24; // hour

                if ($group === 'total-time') {
                    $count_by_hour[$hour . 'h'] += $record[2];

                    if (!isset($top_requests[$record[5]])) {
                        $top_requests[$record[5]] = 0;
                    }
                    $top_requests[$record[5]] += $record[2];
                } elseif ($group === 'sql-time') {
                    $count_by_hour[$hour . 'h'] += $record[3];

                    if (!isset($top_requests[$record[5]])) {
                        $top_requests[$record[5]] = 0;
                    }
                    $top_requests[$record[5]] += $record[3];
                } elseif ($group === 'request-count') {
                    $count_by_hour[$hour . 'h']++;

                    if (!isset($top_requests[$record[5]])) {
                        $top_requests[$record[5]] = 0;
                    }
                    $top_requests[$record[5]] += 1;
                } elseif ($group === 'longest') {
                    if ($count_by_hour[$hour . 'h'] < $record[2]) {
                        $count_by_hour[$hour . 'h'] = $record[2];
                    }

                    if (in_array($record[5], config('apm.hide.longest_requests'))) {
                        continue;
                    }

                    $top_requests[$tmp_longest_i . ' ' . $record[5] . ' - ' . $record[6]] = $record[2];
                    $tmp_longest_i++;
                } elseif ($group === 'longest-sql') {
                    if ($count_by_hour[$hour . 'h'] < $record[3]) {
                        $count_by_hour[$hour . 'h'] = $record[3];
                    }

                    $top_requests[$tmp_longest_i . ' ' . $record[5] . ' - ' . $record[6]] = $record[3];
                    $tmp_longest_i++;
                } elseif ($group === 'user') {
                    if ($record[4] !== 'request') {
                        break;
                    }

                    $count_by_hour[$hour . 'h'] += 1;

                    if (!isset($top_requests[$record[6]])) {
                        $top_requests[$record[6]] = 0;
                    }
                    $top_requests[$record[6]]++;
                } else {
                    throw new \Exception('unknown group');
                }
            }

            if ($group === 'total-time') {
                $top_total_count += $value = array_sum(array_column($matches, 2));
            } elseif ($group === 'sql-time') {
                $top_total_count += $value = array_sum(array_column($matches, 3));
            } elseif ($group === 'request-count') {
                $top_total_count += array_sum($count_by_hour);
            } elseif ($group === 'longest') {
                $top_total_count += count($top_requests) ? max($top_requests) : 0;
            } elseif ($group === 'longest-sql') {
                $top_total_count += count($top_requests) ? max($top_requests) : 0;
            } elseif ($group === 'user') {
                $top_total_count += array_sum($count_by_hour);
            } else {
                throw new \Exception('unknown group');
            }
        }

        arsort($top_requests);
        $top_requests = array_slice($top_requests, 0, config('apm.per_page')); // take top 100

        return compact('count_by_hour', 'top_requests', 'top_total_count');
    }

    // ---------------------------------------- private ----------------------------------------------------------------

    private static function path($minus_hours)
    {
        $name = now()->subHours($minus_hours)->format('Y-m-d_H');

        return storage_path('app/apm/apm-' . $name . '.txt');
    }
}
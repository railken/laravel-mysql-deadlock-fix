<?php

namespace App;

use Closure;
use Exception;
use Illuminate\Database\MySqlConnection as BaseMySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Class DeadlockReadyMySqlConnection.
 */
class MySqlConnection extends BaseMySqlConnection
{
    /**
     * Error code of deadlock exception.
     */
    const DEADLOCK_ERROR_CODE = 40001;

    /**
     * Run a SQL statement.
     *
     * @param string   $query
     * @param array    $bindings
     * @param \Closure $callback
     *
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback, $retry = 3)
    {
        $sql = str_replace_array('\?', $this->prepareBindings($bindings), $query);

        $attempt = (4 - $retry);

        try {
            $result = $callback($query, $bindings);
        } catch (Exception $e) {
            if (((int) $e->getCode() === self::DEADLOCK_ERROR_CODE) && $retry > 0) {
                Log::warning('Transaction has been restarted. Retry '.$attempt.". SQL: {$sql}");

                sleep($attempt * 1);

                return $this->runQueryCallback($query, $bindings, $callback, $retry - 1);
            }

            throw new QueryException(
                $query,
                $this->prepareBindings($bindings),
                $e
            );
        }

        return $result;
    }
}

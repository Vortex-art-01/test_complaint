<?php

declare(strict_types=1);

namespace Complaints\Tests\Integration;

/**
 * Делит SQL-скрипт на выражения по «;» вне строк и комментариев «--».
 * Скрипты модуля не используют $$-строки, поэтому их разбор не нужен.
 */
final class SqlScript
{
    /**
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if (!$inString && $char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $current .= "\n";
                continue;
            }
            if ($char === "'") {
                $inString = !$inString;
            }
            if (!$inString && $char === ';') {
                self::push($statements, $current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        self::push($statements, $current);

        return $statements;
    }

    /**
     * @param list<string> $statements
     */
    private static function push(array &$statements, string $statement): void
    {
        if (trim($statement) !== '') {
            $statements[] = trim($statement);
        }
    }
}

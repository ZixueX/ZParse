<?php
namespace MediaParser;

final class Response
{
    public static function make(int $retcode, string $retdesc, $data, bool $succ): array
    {
        return [
            'retcode' => $retcode,
            'retdesc' => $retdesc,
            'data' => $data,
            'succ' => $succ,
        ];
    }
}

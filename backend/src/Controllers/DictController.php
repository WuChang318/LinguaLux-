<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\DictService;
use App\Response;

class DictController
{
    public static function lookup(array $params): void
    {
        Auth::require();
        $word = trim((string) rawurldecode($params['word']));
        if ($word === '') {
            Response::error('请输入单词', 400);
        }
        Response::json(DictService::lookup($word));
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Request;
use App\Response;
use App\Upload;

class UploadController
{
    /** POST /admin/upload —— 上传图片（base64），返回 { url } */
    public static function image(array $params): void
    {
        Auth::admin();
        $data = (string) Request::input('image', '');
        if ($data === '') {
            Response::error('缺少图片数据', 400);
        }
        Response::json(['url' => Upload::saveBase64Image($data)]);
    }
}

<?php

namespace App\Http\Controllers\prueba;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PruebaController extends Controller
{
    /**
     * Index.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return response()->json([
            'res' => true,
            'msg' => $request->msg
        ]);
    }

}

<?php

namespace App\Http\Controllers\image;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function image_upload()
    {
        return view('image_upload');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function upload_post_image(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        if($request->hasfile('image'))
        {
            $file = $request->file('image');
            $imageName=time().$file->getClientOriginalName();
            $filePath = 'images/' . $imageName;
            Storage::disk('s3')->put($filePath, file_get_contents($file));
            return back()->with('success','You have successfully upload image.');
        }
    }
    public function show(){
        dd(Storage::disk('s3')->allFiles(''));
        // var_dump("Estamos aqui");
    }
    public function sube_archivos_s3(){
        $files = File::allFiles(resource_path()."/pdf/");
        foreach ($files as $file) {
            Storage::disk('s3')->put("pdf/".basename($file), file_get_contents($file));
        }
        File::delete($files);

    }
}


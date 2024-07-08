<?php

use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});



// Getting Product Data From DB
Route::get('api/products' , function(){
    $product = DB::select('SELECT * FROM products LEFT JOIN product_category ON products.id = product_category.product_id LEFT JOIN product_image_and_color ON products.id = product_image_and_color.product_id LEFT JOIN product_reviews ON products.id = product_reviews.product_id LEFT JOIN product_stock ON products.id = product_stock.product_id LEFT JOIN product_reviewers ON product_reviews.reviewer_id = product_reviewers.id');
    return response(Json::encode($product));
});



// Product Upload Post Request -- not completed yet
Route::post('api/dashboard/product-upload', function(Request $request){


        if($request->hasFile('images')){
            foreach($request->file('images') as $file){

                $file_name = time() . "-" . $file->getClientOriginalName();

                $file->storeAs('images', $file_name, 'public');


            }

            return response()->json(['message' => 'Files uploaded successfully'], 200);

        }

        // When file is not uploaded
        return response()->json(['message' => 'No files were uploaded'], 400);
});

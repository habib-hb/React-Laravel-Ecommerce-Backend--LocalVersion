<?php

use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

            // These variables will get filled through the foreach loop below
            $product_images = [];
            $product_variants = [];


        if($request->hasFile('images')){
            foreach($request->file('images') as $file){

                // $file_name = time() . "-" . $file->getClientOriginalName();

                // Get the file name without extension
                $file_name_with_extension = $file->getClientOriginalName();
                $file_name = pathinfo($file_name_with_extension, PATHINFO_FILENAME);

                // Store the file
                $file_path = $file->storeAs('images', $file_name_with_extension, 'public');

                // Generate the URL
                $url = Storage::url($file_path);

                // Add the URL to the array
                $product_images[] = $url;

                // Add the file name to the array
                $product_variants[] = $file_name;


            }

            $product_name = $request->product_name;
            $product_description = $request->description;
            $product_price = $request->price;
            $product_category = $request->category;
            $product_brand = $request->brand;
            $product_stock = $request->instock_amount;
            $product_color_placeholder = "#ff2245";



            // *** The Database operations
                $product_database = DB::table('products')->insertGetId([
                    'name' => $product_name,
                    'description' => $product_description,
                    'price' => $product_price,
                    'brand' => $product_brand,
                ]);


                $product_category_database = DB::table('product_category')->insert([
                    'product_id' => $product_database, // variable itself hosts the id
                    'category' => $product_category,
                ]);

                $product_stock_database = DB::table('product_stock')->insert([
                    'product_id' => $product_database, // variable itself hosts the id
                    'stock_amount' => $product_stock,
                ]);




            return response()->json(['message' => 'Files uploaded successfully'], 200);

        }

        // When file is not uploaded
        return response()->json(['message' => 'No files were uploaded'], 400);
});

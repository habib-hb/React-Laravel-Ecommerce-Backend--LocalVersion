<?php

use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
// use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});



// Getting Product Data From DB
Route::get('api/products' , function(){
    $product = DB::select('SELECT * FROM products LEFT JOIN product_category ON products.id = product_category.product_id LEFT JOIN product_image_and_color ON products.id = product_image_and_color.product_id LEFT JOIN product_reviews ON products.id = product_reviews.product_id LEFT JOIN product_stock ON products.id = product_stock.product_id LEFT JOIN product_customers ON product_reviews.customer_id = product_customers.customer_id');
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

                //Product Images and variant operation
                foreach($product_images as $key => $image){
                    DB::table('product_image_and_color')->insert([
                        'product_id' => $product_database,
                        'image' => 'http://127.0.0.1:8000' . $image,
                        'color_name' => $product_variants[$key],
                        'color_code' => $product_color_placeholder,
                    ]);
                }




            return response()->json(['message' => 'Files uploaded successfully'], 200);

        }

        // When file is not uploaded
        return response()->json(['message' => 'No files were uploaded'], 400);
});


Route::get('/storage/images/{file_name}', function ($file_name) {
    $path = storage_path('app/public/images/' . $file_name);
    if (!file_exists($path)) {
        abort(404);
    }
    $file = File::get($path);
    $type = File::mimeType($path);
    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);
    return $response;
});



// Review Message processing
Route::post('api/dashboard/review-upload', function(Request $request){

        //Validating the request inputs
        $request->validate([
            //formDataToSend.append('name', nameData ?? '');
            // formDataToSend.append('user_id', userIdData  ?? '');
            // formDataToSend.append('product_id', productIdData ?? '');
            // formDataToSend.append('review_text', reviewTextData ?? '');
            // formDataToSend.append('selected_stars', selectedStarsData ?? '');
            // formDataToSend.append('review_image', reviewImageFile ?? '');

            'name' => 'required',
            'user_id' => 'required',
            'product_id' => 'required',
            'review_text' => 'required',
            'selected_stars' => 'required',
             ]);


             // Declaring it beforehand to avoid any type of error if there's no image uploaded
             $review_image_url=null;
        if($request->hasFile('review_image')){
        // Saving the review image in the local storage
        $review_image = $request->review_image;
        $review_image_name = time() . "--" . $review_image->getClientOriginalName();
        $review_image->storeAs('images', $review_image_name, 'public');
        // Review Image Url
        $review_image_url = Storage::url('images/' . $review_image_name);
        // Review Image Name without extension
        $review_image_name_without_extension = pathinfo($review_image_name, PATHINFO_FILENAME);
        }

        //DB update or insert if the user doesn't exist
        $availability_checking = DB::select('SELECT * from product_customers WHERE customer_id = ?', [$request->user_id]);

        if(count($availability_checking) == 0){
            $review_image_url ? DB::table('product_customers')->insert([
                    'customer_name' => $request->name,
                    'customer_id' => $request->user_id,
                    'customer_avatar' => 'http://127.0.0.1:8000' . $review_image_url,
                ]) : DB::table('product_customers')->insert([
                    'customer_name' => $request->name,
                    'customer_id' => $request->user_id,
                ]);
             }
        else{

           $review_image_url ? DB::table('product_customers')->where('customer_id', $request->user_id)->update([
                'customer_name' => $request->name,
                'customer_avatar' => 'http://127.0.0.1:8000' . $review_image_url,
            ]) : null;

        }

        //DB operation for the review insertion
        DB::table('product_reviews')->insert([
            'product_id' => intval($request->product_id),
            'customer_name' => $request->name,
            'review' => $request->review_text,
            'customer_id' => $request->user_id,
            'rating' => intval($request->selected_stars),
        ]);


        // Extracting the image url from DB
       $image_for_javascript = DB::select('SELECT customer_avatar FROM product_customers WHERE customer_id = ?', [$request->user_id]);


        return response()->json(['message' => 'File uploaded successfully' , 'image_url_javascript' => $image_for_javascript[0]->customer_avatar , 'user_name' => $request->name, 'rating' => $request->selected_stars, 'review_text' => $request->review_text], 200);
});

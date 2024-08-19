<?php

use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;










Route::get('/', function () {
    return view('welcome');
});








Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');








Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});










// Getting Product Data From DB
Route::get('api/products' , function(){
    $product = DB::select('SELECT * FROM products LEFT JOIN product_category ON products.id = product_category.product_id LEFT JOIN product_image_and_color ON products.id = product_image_and_color.product_id LEFT JOIN product_reviews ON products.id = product_reviews.product_id LEFT JOIN product_stock ON products.id = product_stock.product_id LEFT JOIN product_customers ON product_reviews.customer_id = product_customers.customer_id');
    return response(Json::encode($product));
});










// Product Upload Post Request -- not completed yet
Route::post('api/dashboard/product-upload', function(Request $request){
            $request->validate([
                'admin_email' => 'required',
                'product_name' => 'required',
                'description' => 'required',
                'price' => 'required',
                'category' => 'required',
                'brand' => 'required',
                'instock_amount' => 'required',
                'images' => 'required',
            ]);

            // Admin Check through session data
            $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$request->admin_email]);

            if(count($admin_check) > 0){

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

    }else{

        return response()->json(['message' => 'Admin not found. Thus the upload request has been denied.' ], 200);
    }
        });









// Product Update Post Request -- not completed yet
Route::post('api/dashboard/product_update', function(Request $request){


    $request->validate([
        'user_email' => 'required',
    ]);


    // Admin Validation Proceedure
    $user_email = $request->user_email;

    $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$user_email]);

    if(count($admin_check) > 0){


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

    }


    $product_id = $request->product_id;
    $product_name = $request->product_name;
    $product_description = $request->description;
    $product_price = $request->price;
    $product_category = $request->category;
    $product_brand = $request->brand;
    $product_stock = $request->instock_amount;
    $product_color_placeholder = "#ff2245";



    // *** The Database operations
        $product_database = DB::table('products')
        ->where('id', $product_id)
        ->update([
            'name' => $product_name,
            'description' => $product_description,
            'price' => $product_price,
            'brand' => $product_brand,
        ]);


        $product_category_database = DB::table('product_category')->where('product_id', $product_id)->update([
            'category' => $product_category,
        ]);

        $product_stock_database = DB::table('product_stock')->where('product_id', $product_id)->update([
            'stock_amount' => $product_stock,
        ]);

        //Product Images and variant operation
        if(count($product_images) != 0){
            //Delete the old images
            DB::table('product_image_and_color')->where('product_id', $product_id)->delete();

            //New Product Images and variant insertion operation
            foreach($product_images as $key => $image){
                DB::table('product_image_and_color')->insert([
                    'product_id' => $product_id,
                    'image' => 'http://127.0.0.1:8000' . $image,
                    'color_name' => $product_variants[$key],
                    'color_code' => $product_color_placeholder,
                ]);
        }
    }

    return response()->json(['message' => 'Admin found. Thus the upload request has been allowed. The admin email is >>> ' . $user_email], 200);

    }else{
        return response()->json(['message' => 'Admin not found. Thus the upload request has been denied.' ], 400);
    }



    });









// Deleting the specified product
Route::get('api/dashboard/product_delete/{id}', function($id, Request $request){

    $user_email = $request->query('email');

    $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$user_email]);

    if(count($admin_check) > 0){


    DB::table('products')->where('id', $id)->delete();
    DB::table('product_category')->where('product_id', $id)->delete();
    DB::table('product_stock')->where('product_id', $id)->delete();
    DB::table('product_image_and_color')->where('product_id', $id)->delete();
    DB::table('product_reviews')->where('product_id', $id)->delete();


        return response()->json(['message' => 'Product deleted successfully from the database. The admin email is >>> ' . $user_email], 200);


    }else{

        return response()->json(['message' => 'Admin not found. Thus the delete request has been denied.' ], 400);

    }



});







// Loading Images from the storage -aka- Backend
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









// Github Login
Route::get('/auth/redirect', function () {
            return Socialite::driver('github')->redirect();
        });








// Github Login Redirect and data processing
Route::get('/auth/callback', function () {
            $githubUser = Socialite::driver('github')->user();

            $user = User::firstOrCreate([
                // 'github_id' => $githubUser->id,
                'email' => $githubUser->email
            ], [
                'name' => $githubUser->name,
                // 'github_id' => $githubUser->id,
                // 'email' => $githubUser->email,
                // 'remember_token' => 'wow again',
                // 'github_refresh_token' => $githubUser->refreshToken,
                'password' => 'random_password'
            ]);

            Auth::login($user , true);

            // return redirect('http://localhost:3000/');
            return redirect('http://localhost:3000/github-login/' . $user->id);
            // return redirect('http://127.0.0.1:8000/');
            // return redirect('/');
        });









// Signing / register In Users with Credentials
Route::post('auth/user/create', function (Request $request) {

            $request->validate([
                'name' => 'required',
                'email' => 'required|email',
                'password' => 'required',
            ]);


            // Creating The User Conditionally
                //Checking if the user already exists
                $availability_checking = DB::select('SELECT * from users WHERE email = ?', [$request->email]);

                if(count($availability_checking) == 0){
                    $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                ]);

            }else{
                return response()->json(['message' => 'User With This Email Already Exists'], 400);
            }



            // Logging the user in
            Auth::login($user , true);

            // Doing Admin confirmation based on email address
            $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$request->email]);

            if(count($admin_check) > 0){
               // Setting Session
                session(['admin' => $request->email]);

            }

            return redirect('http://127.0.0.1:8000/');


        });









Route::post('/api/login', function (Request $request) {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();
                return response()->json(['message' => 'Login successful', 'user' => $user], 200);
            } else {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }


        });









// The user data extraction
Route::post('/api/user_data_retrive' , function(Request $request){
        $request->validate([
            'email' => 'required|email',
        ]);

          // Retrieve the email from the request
    $email = $request->email;

    // Find the user by email
    $user = User::where('email', $email)->first();

    // Check if the user exists
    if ($user) {

        $avatar = DB::select('SELECT customer_avatar FROM product_customers WHERE customer_id = ?', [$user->id]);
        // Return the user's data as a JSON response

        if($avatar){
            return response()->json([
                'success' => true,
                'data' => $user,
                'avatar' => $avatar[0]->customer_avatar,
            ]);
        }else{
            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        }
    } else {
        // Return an error message if the user does not exist
        return response()->json([
            'success' => false,
            'message' => 'User not found'
        ], 404);
    }

    });









// Getting the user Email data
Route::post('api/get_github_info', function (Request $request) {

        $request->validate([
            'laravel_id' => 'required',
        ]);


        $user = User::where('id', $request->laravel_id)->first();


        return response()->json(['email' => $user->email], 200);

    });








// User's profile picture upload
Route::post('api/dashboard/profile_picture_upload' , function(Request $request){

        $request->validate([
            'user_email' => 'required',
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $profile_picture = $request->profile_picture;
        $profile_picture_name = time() . "--" . $profile_picture->getClientOriginalName();
        $profile_picture->storeAs('images', $profile_picture_name, 'public');
            // $profile_picture_url = asset('storage/images/' . $profile_picture_name);

        // Profile Picture Url
        $profile_picture_storage_url = Storage::url('images/' . $profile_picture_name);

        // Profile Picture Full Url
            $profile_picture_full_url = asset($profile_picture_storage_url);
        // $profile_picture_full_url = 'http://127.0.0.1:8000' . $profile_picture_storage_url;

        // Finding the user from the Email
        $user_details = User::where('email', $request->user_email)->first();

        // Creating or Updating Customer data From User data
        $existingCustomer = DB::table('product_customers')
        ->where('customer_id', $user_details->id)
        ->first();

            if ($existingCustomer) {
                // Update the existing record
                DB::table('product_customers')
                    ->where('customer_id', $user_details->id)
                    ->update([
                        'customer_name' => $user_details->name,
                        'customer_avatar' => $profile_picture_full_url,
                    ]);
            } else {
                // Insert a new record
                DB::table('product_customers')
                    ->insert([
                        'customer_id' => $user_details->id,
                        'customer_name' => $user_details->name,
                        'customer_avatar' => $profile_picture_full_url,
                    ]);
            }


        return response()->json(['profile_picture_full_url' => $profile_picture_full_url], 200);

    });










Route::get('api/dashboard/comment_delete/{comment_id}' , function($comment_id , Request $request){

    // Checking if the user is an Admin or not
    $user_email = $request->query('email');

    $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$user_email]);

    if(count($admin_check) > 0){

    DB::table('product_reviews')->where('review_id', $comment_id)->delete();

    return response()->json(['message' => 'Comment deleted successfully. The Admin email is >>> ' . $user_email], 200);

    }else{
        return response()->json(['message' => 'Admin not found. Thus the delete request has been denied.'], 400);
    }

});









Route::get('api/customers' , function(Request $request){

    // Admin Checking Functionality
    $user_email = $request->query('email');

    $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$user_email]);

    if(count($admin_check) > 0){

    $customer = DB::select('SELECT * FROM product_customers LEFT JOIN users ON product_customers.customer_id = users.id');

    return response(Json::encode($customer));

    }else{

        return response()->json(['message' => 'Admin not found. Thus the request for the data has been denied.'], 400);

    }
});









Route::get('api/dashboard/customer_delete/{customer_id}' , function($customer_id){

    DB::table('product_customers')->where('customer_id', $customer_id)->delete();
    DB::table('users')->where('id', $customer_id)->delete();


    return response()->json(['message' => 'Customer deleted successfully'], 200);
});










Route::post('api/logout', function (Request $request) {
    $request->validate([
        'email' => 'required',
    ]);


    $user = User::where('email', $request->email)->first();

    if($user){

        // Checking if the user is an Admin or not
        $admin_check= DB::select('SELECT * from admins WHERE admin_email = ?', [$request->email]);

        if(count($admin_check) > 0){

            // Deleting Session
            session()->forget('admin');

            return response()->json(['message' => 'Admin logged out successfully. The user Id of the Admin is >>>' . $user->id], 200);

        }

    return response()->json(['message' => 'User logged out successfully. The user Id is >>>' . $user->id], 200);

    }else{

        return response()->json(['message' => 'User not found'], 400);

    }
});









Route::get('api/admins' , function(Request $request){

    $user_email = $request->query('email');

    // Checking if the user is an Admin or not
    $admin_check = DB::select('SELECT * from admins WHERE admins.admin_email = ?', [$user_email]);

    if(count($admin_check) > 0){

    $admin = DB::select('SELECT * FROM admins LEFT JOIN product_customers ON admins.user_id = product_customers.customer_id');

    return response(Json::encode($admin));

    }else{

        return response()->json(['message' => 'Admin not found. Thus the request for the data has been denied.'], 400);

    }
});









Route::get('api/dashboard/admin_delete/{admin_id}' , function($admin_id , Request $request){

    $user_email = $request->query('email');

    // Checking if the user is an Admin or not
    $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$user_email]);

    if(count($admin_check) > 0){
    DB::table('admins')->where('user_id', $admin_id)->delete();

    return response()->json(['message' => 'Admin deleted successfully'], 200);

    }else{
        return response()->json(['message' => 'Admin not found. Thus the delete request has been denied.'], 400);
    }

});










Route::get('api/add_admin_get_customers' , function(Request $request){

    $user_email = $request->query('email');

    // Checking if the user is an Admin or not
    $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$user_email]);

    if(count($admin_check) > 0){

    $admin = DB::select('SELECT * FROM product_customers LEFT JOIN admins ON admins.user_id = product_customers.customer_id LEFT JOIN users ON product_customers.customer_id = users.id');

    return response(Json::encode($admin));

    }else{

        return response()->json(['message' => 'Admin not found. Thus the request for the data has been denied.'], 400);

    }


});










Route::post('api/dashboard/add_admin_insert_customer' , function(Request $request){

    $request->validate([
        'targeted_customer_id' => 'required',
        'admin_email' => 'required',
    ]);

    // Checking if the user is an Admin or not
    $admin_check = DB::select('SELECT * from admins WHERE admin_email = ?', [$request->admin_email]);

    if(count($admin_check) > 0){


            $targeted_customer = DB::select('SELECT * FROM product_customers LEFT JOIN users ON product_customers.customer_id = users.id WHERE customer_id = ?', [$request->targeted_customer_id]);

            if(count($targeted_customer) > 0){

                DB::table('admins')->insert(['user_id' => $targeted_customer[0]->customer_id,
                                            'admin_email' => $targeted_customer[0]->email,
                                            'admin_type' => 'all'
                                            ]);

                return response()->json(['message' => 'Admin added successfully'], 200);

            }else{

                return response()->json(['message' => 'Customer not found. Thus the request for the data has been denied.'], 400);

            }

    }else{
        return response()->json(['message' => 'Admin not found. Thus the request for the data has been denied.'], 400);
    }

});









Route::post('api/dashboard/order_placement' , function(Request $request){

    $request->validate([
        'ordered_products' => 'required',
        'orderer_email' => 'required',
    ]);

    $ordered_products = $request->ordered_products;

    $orderer_email = $request->orderer_email;


    // Extracting User Data
    $user = DB::table('users')->where('email', $orderer_email)->first();
    $user_id = $user->id;

    if($user){

        DB::table('orders')->insert(['user_id' => $user_id,
                                     'user_email' => $orderer_email,
                                     'orders_data' => $ordered_products]);

        return response()->json(['message' => 'Order placed successfully'], 200);

    }else{

        return response()->json(['message' => 'User not found. Thus the request for the data has been denied.'], 400);

    }
});




require __DIR__.'/auth.php';

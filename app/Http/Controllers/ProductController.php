<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductController extends Controller
{
    // GET all products
    public function index()
    {
        $products = Product::with('category')->latest()->get();
        return response()->json(['success'=>true, 'data'=>$products], 200);
    }

    // GET single product
    public function show($id)
    {
        $product = Product::with('category')->find($id);
        if (!$product) return response()->json(['success'=>false,'message'=>'Product not found'],404);
        return response()->json(['success'=>true,'data'=>$product],200);
    }

    // CREATE product
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'=>'required|exists:categories,id',
            'name'=>'required|string|max:255',
            'price'=>'required|numeric|min:0',
            'stock'=>'required|integer|min:0',
            'description'=>'nullable|string',
            'image'=>'nullable|image'
        ]);

        if ($request->hasFile('image')) {
            $uploadedFile = Cloudinary::upload(
                $request->file('image')->getRealPath(),
                ['folder'=>'products/images']
            );
            $data['image_url'] = $uploadedFile->getSecurePath();
            $data['cloudinary_id'] = $uploadedFile->getPublicId();
        }

        $product = Product::create($data);

        return response()->json(['success'=>true,'message'=>'Product created successfully','data'=>$product],201);
    }

    // UPDATE product
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['success'=>false,'message'=>'Product not found'],404);

        $data = $request->validate([
            'category_id'=>'required|exists:categories,id',
            'name'=>'required|string|max:255',
            'price'=>'required|numeric|min:0',
            'stock'=>'required|integer|min:0',
            'description'=>'nullable|string',
            'image'=>'nullable|image'
        ]);

        if ($request->hasFile('image')) {
            // delete old image
            if ($product->cloudinary_id) {
                Cloudinary::destroy($product->cloudinary_id);
            }

            $uploadedFile = Cloudinary::upload(
                $request->file('image')->getRealPath(),
                ['folder'=>'products/images']
            );
            $data['image_url'] = $uploadedFile->getSecurePath();
            $data['cloudinary_id'] = $uploadedFile->getPublicId();
        }

        $product->update($data);

        return response()->json(['success'=>true,'message'=>'Product updated successfully','data'=>$product],200);
    }

    // DELETE product
    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['success'=>false,'message'=>'Product not found'],404);

        if ($product->cloudinary_id) {
            Cloudinary::destroy($product->cloudinary_id);
        }

        $product->delete();

        return response()->json(['success'=>true,'message'=>'Product deleted successfully'],200);
    }
}

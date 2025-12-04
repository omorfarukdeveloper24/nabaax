<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Toastr;
use Image;
use File;
use DB;
class GeneralSettingController extends Controller
{
    function __construct()
    {
        $this->middleware('permission:setting-list|setting-create|setting-edit|setting-delete', ['only' => ['index','store']]);
        $this->middleware('permission:setting-create', ['only' => ['create','store']]);
        $this->middleware('permission:setting-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:setting-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $show_data = GeneralSetting::orderBy('id','DESC')->get();
        return view('backEnd.settings.index',compact('show_data'));
    }
    public function create()
    {
        return view('backEnd.settings.create');
    }
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'white_logo' => 'required',
            'favicon' => 'required',
            'status' => 'required',
        ]);

        // image with intervention 
        $image = $request->file('white_logo');
        $name =  time().'-'.$image->getClientOriginalName();
        $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name);
        $name = strtolower(preg_replace('/\s+/', '-', $name));
        $uploadpath = 'public/uploads/settings/';
        $imageUrl = $uploadpath.$name; 
        $img=Image::make($image->getRealPath());
        $img->encode('webp', 90);
        $width = '';
        $height = '';
        $img->height() > $img->width() ? $width=null : $height=null;
        $img->resize($width, $height);
        $img->save($imageUrl);

        // dark logo
        $image2 = $request->file('dark_logo');
        $name2 =  time().'-'.$image2->getClientOriginalName();
        $name2 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name2);
        $name2 = strtolower(preg_replace('/\s+/', '-', $name2));
        $uploadpath2 = 'public/uploads/settings/';
        $image2Url = $uploadpath2.$name2; 
        $img2=Image::make($image2->getRealPath());
        $img2->encode('webp', 90);
        $width2 = '';
        $height2 = '';
        $img2->height() > $img2->width() ? $width2=null : $height2=null;
        $img2->resize($width2, $height2);
        $img2->save($image2Url);

        // image with intervention 
        $image3 = $request->file('favicon');
        $name3 =  time().'-'.$image3->getClientOriginalName();
        $name3 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name3);
        $name3 = strtolower(preg_replace('/\s+/', '-', $name3));
        $uploadpath3 = 'public/uploads/settings/';
        $image3Url = $uploadpath3.$name3; 
        $img3=Image::make($image3->getRealPath());
        $img3->encode('webp', 90);
        $width3 = '';
        $height3 = '';
        $img3->height() > $img3->width() ? $width3=null : $height3=null;
        $img3->resize($width3, $height3);
        $img3->save($image3Url);
        
        $image4 = $request->file('top_offer_image');
        if($image4){
            $image4 = $request->file('top_offer_image');
            $name4 =  time().'-'.$image4->getClientOriginalName();
            $name4 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name4);
            $name4 = strtolower(preg_replace('/\s+/', '-', $name4));
            $uploadpath4 = 'public/uploads/settings/';
            $image4Url = $uploadpath4.$name4; 
            $img4=Image::make($image4->getRealPath());
            $img4->encode('webp', 90);
            $width4 = '';
            $height4 = '';
            $img4->height() > $img4->width() ? $width4=null : $height4=null;
            $img4->resize($width4, $height4);
            $img4->save($image4Url);
            $input['top_offer_image'] = $image4Url;
        }else{
            $input['top_offer_image'] = NULL;
        }
        
        $image5 = $request->file('free_shipping_image');
        if($image5){
            $image5 = $request->file('free_shipping_image');
            $name5 = time().'-'.$image5->getClientOriginalName();
            $name5 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name5);
            $name5 = strtolower(preg_replace('/\s+/', '-', $name5));
            $uploadpath5 = 'public/uploads/settings/';
            $image5Url = $uploadpath5.$name5; 
            $img5 = Image::make($image5->getRealPath());
            $img5->encode('webp', 90);
            $width5 = '';
            $height5 = '';
            $img5->height() > $img5->width() ? $width5=null : $height5=null;
            $img5->resize($width5, $height5);
            $img5->save($image5Url);
            $input['free_shipping_image'] = $image5Url;
        } else {
            $input['free_shipping_image'] = NULL;
        }
        
        $image6 = $request->file('best_selling_image');
        if($image6){
            $image6 = $request->file('best_selling_image');
            $name6 = time().'-'.$image6->getClientOriginalName();
            $name6 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name6);
            $name6 = strtolower(preg_replace('/\s+/', '-', $name6));
            $uploadpath6 = 'public/uploads/settings/';
            $image6Url = $uploadpath6.$name6; 
            $img6 = Image::make($image6->getRealPath());
            $img6->encode('webp', 90);
            $width6 = '';
            $height6 = '';
            $img6->height() > $img6->width() ? $width6=null : $height6=null;
            $img6->resize($width6, $height6);
            $img6->save($image6Url);
            $input['best_selling_image'] = $image6Url;
        } else {
            $input['best_selling_image'] = NULL;
        }
        
        $image7 = $request->file('new_product_image');
        if($image7){
            $image7 = $request->file('new_product_image');
            $name7 = time().'-'.$image7->getClientOriginalName();
            $name7 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name7);
            $name7 = strtolower(preg_replace('/\s+/', '-', $name7));
            $uploadpath7 = 'public/uploads/settings/';
            $image7Url = $uploadpath7.$name7; 
            $img7 = Image::make($image7->getRealPath());
            $img7->encode('webp', 90);
            $width7 = '';
            $height7 = '';
            $img7->height() > $img7->width() ? $width7=null : $height7=null;
            $img7->resize($width7, $height7);
            $img7->save($image7Url);
            $input['new_product_image'] = $image7Url;
        } else {
            $input['new_product_image'] = NULL;
        }
        
        $image8 = $request->file('shop_image');
        if($image8){
            $image8 = $request->file('shop_image');
            $name8 = time().'-'.$image8->getClientOriginalName();
            $name8 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name8);
            $name8 = strtolower(preg_replace('/\s+/', '-', $name8));
            $uploadpath8 = 'public/uploads/settings/';
            $imageUrl8 = $uploadpath8.$name8; 
            $img8 = Image::make($image8->getRealPath());
            $img8->encode('webp', 90);
            $width8 = '';
            $height8 = '';
            $img8->height() > $img8->width() ? $width8=null : $height8=null;
            $img8->resize($width8, $height8);
            $img8->save($imageUrl8);
            $input['shop_image'] = $imageUrl8;
        } else {
            $input['shop_image'] = NULL;
        }


        $input = $request->all();
        $input['white_logo'] = $imageUrl;
        $input['dark_logo'] = $image2Url;
        $input['favicon'] = $image3Url;
        $input['top_offer_image'] = $image4Url;
        $input['free_shipping_image'] = $image5Url;
        $input['best_selling_image'] = $image6Url;
        $input['new_product_image'] = $image7Url;
        $input['shop_image'] = $imageUrl8;
        GeneralSetting::create($input);
        Toastr::success('Success','Data insert successfully');
        return redirect()->route('settings.index');
    }
    
    public function edit($id)
    {
        $edit_data = GeneralSetting::find($id);
        return view('backEnd.settings.edit',compact('edit_data'));
    }
    
    public function update(Request $request)
    {
        $this->validate($request, [
            'name' => 'required'
        ]);
        $update_data = GeneralSetting::find($request->id);
        $input = $request->all();
        // new white logo
        $image = $request->file('white_logo');
        if($image){
            // image with intervention 
            $image = $request->file('white_logo');
            $name =  time().'-'.$image->getClientOriginalName();
            $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name);
            $name = strtolower(preg_replace('/\s+/', '-', $name));
            $uploadpath = 'public/uploads/settings/';
            $imageUrl = $uploadpath.$name; 
            $img=Image::make($image->getRealPath());
            $img->encode('webp', 90);
            $width = '';
            $height = '';
            $img->height() > $img->width() ? $width=null : $height=null;
            $img->resize($width, $height);
            $img->save($imageUrl);
            $input['white_logo'] = $imageUrl;
        }else{
            $input['white_logo'] = $update_data->white_logo;
        }
        // new dark logo
        $image2 = $request->file('dark_logo');
        if($image2){
            // image with intervention 
            $image2 = $request->file('dark_logo');
            $name2 =  time().'-'.$image2->getClientOriginalName();
            $name2 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name2);
            $name2 = strtolower(preg_replace('/\s+/', '-', $name2));
            $uploadpath2 = 'public/uploads/settings/';
            $image2Url = $uploadpath2.$name2; 
            $img2=Image::make($image2->getRealPath());
            $img2->encode('webp', 90);
            $width2 = '';
            $height2 = '';
            $img2->height() > $img2->width() ? $width2=null : $height2=null;
            $img2->resize($width2, $height2);
            $img2->save($image2Url);
            $input['dark_logo'] = $image2Url;
        }else{
            $input['dark_logo'] = $update_data->dark_logo;
        }

        // new favicon image
        
        $image3 = $request->file('favicon');
        if($image3){
            $image3 = $request->file('favicon');
            $name3 =  time().'-'.$image3->getClientOriginalName();
            $name3 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name3);
            $name3 = strtolower(preg_replace('/\s+/', '-', $name3));
            $uploadpath3 = 'public/uploads/settings/';
            $image3Url = $uploadpath3.$name3; 
            $img3=Image::make($image3->getRealPath());
            $img3->encode('webp', 90);
            $width3 = '';
            $height3 = '';
            $img3->height() > $img3->width() ? $width3=null : $height3=null;
            $img3->resize($width3, $height3);
            $img3->save($image3Url);
            $input['favicon'] = $image3Url;
        }else{
            $input['favicon'] = $update_data->favicon;
        }
        
        $image4 = $request->file('top_offer_image');
        if($image4){
            $image4 = $request->file('top_offer_image');
            $name4 =  time().'-'.$image4->getClientOriginalName();
            $name4 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name4);
            $name4 = strtolower(preg_replace('/\s+/', '-', $name4));
            $uploadpath4 = 'public/uploads/settings/';
            $image4Url = $uploadpath4.$name4; 
            $img4=Image::make($image4->getRealPath());
            $img4->encode('webp', 90);
            $width4 = '';
            $height4 = '';
            $img4->height() > $img4->width() ? $width4=null : $height4=null;
            $img4->resize($width4, $height4);
            $img4->save($image4Url);
            $input['top_offer_image'] = $image4Url;
        }else{
            $input['top_offer_image'] = $update_data->top_offer_image;
        }
        
        $image5 = $request->file('free_shipping_image');
        if($image5){
            $image5 = $request->file('free_shipping_image');
            $name5 = time().'-'.$image5->getClientOriginalName();
            $name5 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name5);
            $name5 = strtolower(preg_replace('/\s+/', '-', $name5));
            $uploadpath5 = 'public/uploads/settings/';
            $image5Url = $uploadpath5.$name5; 
            $img5 = Image::make($image5->getRealPath());
            $img5->encode('webp', 90);
            $width5 = '';
            $height5 = '';
            $img5->height() > $img5->width() ? $width5=null : $height5=null;
            $img5->resize($width5, $height5);
            $img5->save($image5Url);
            $input['free_shipping_image'] = $image5Url;
        } else {
            $input['free_shipping_image'] = $update_data->free_shipping_image;
        }
        
        $image6 = $request->file('best_selling_image');
        if($image6){
            $image6 = $request->file('best_selling_image');
            $name6 = time().'-'.$image6->getClientOriginalName();
            $name6 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name6);
            $name6 = strtolower(preg_replace('/\s+/', '-', $name6));
            $uploadpath6 = 'public/uploads/settings/';
            $image6Url = $uploadpath6.$name6; 
            $img6 = Image::make($image6->getRealPath());
            $img6->encode('webp', 90);
            $width6 = '';
            $height6 = '';
            $img6->height() > $img6->width() ? $width6=null : $height6=null;
            $img6->resize($width6, $height6);
            $img6->save($image6Url);
            $input['best_selling_image'] = $image6Url;
        } else {
            $input['best_selling_image'] = $update_data->best_selling_image;
        }
        
        
        $image7 = $request->file('new_product_image');
        if($image7){
            $image7 = $request->file('new_product_image');
            $name7 = time().'-'.$image7->getClientOriginalName();
            $name7 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name7);
            $name7 = strtolower(preg_replace('/\s+/', '-', $name7));
            $uploadpath7 = 'public/uploads/settings/';
            $image7Url = $uploadpath7.$name7; 
            $img7 = Image::make($image7->getRealPath());
            $img7->encode('webp', 90);
            $width7 = '';
            $height7 = '';
            $img7->height() > $img7->width() ? $width7=null : $height7=null;
            $img7->resize($width7, $height7);
            $img7->save($image7Url);
            $input['new_product_image'] = $image7Url;
        } else {
            $input['new_product_image'] = $update_data->new_product_image;
        }
        
        $image8 = $request->file('shop_image');
        if($image8){
            $image8 = $request->file('shop_image');
            $name8 = time().'-'.$image8->getClientOriginalName();
            $name8 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name8);
            $name8 = strtolower(preg_replace('/\s+/', '-', $name8));
            $uploadpath8 = 'public/uploads/settings/';
            $imageUrl8 = $uploadpath8.$name8; 
            $img8 = Image::make($image8->getRealPath());
            $img8->encode('webp', 90);
            $width8 = '';
            $height8 = '';
            $img8->height() > $img8->width() ? $width8=null : $height8=null;
            $img8->resize($width8, $height8);
            $img8->save($imageUrl8);
            $input['shop_image'] = $imageUrl8;
        } else {
            $input['shop_image'] = $update_data->shop_image;
        }
        
        $input['status'] = $request->status?1:0;
        $update_data->update($input);

        Toastr::success('Success','Data update successfully');
        return redirect()->route('settings.index');
    }
 
    public function inactive(Request $request)
    {
        $inactive = GeneralSetting::find($request->hidden_id);
        $inactive->status = 0;
        $inactive->save();
        Toastr::success('Success','Data inactive successfully');
        return redirect()->back();
    }
    public function active(Request $request)
    {
        $active = GeneralSetting::find($request->hidden_id);
        $active->status = 1;
        $active->save();
        Toastr::success('Success','Data active successfully');
        return redirect()->back();
    }
    public function destroy(Request $request)
    {
        $delete_data = GeneralSetting::find($request->hidden_id);
        File::delete($delete_data->image);
        $delete_data->delete();
        Toastr::success('Success','Data delete successfully');
        return redirect()->back();
    }
}

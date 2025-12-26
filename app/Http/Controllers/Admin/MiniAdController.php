<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Google\Cloud\Storage\StorageClient;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;
use App\Models\MiniAd;
use Toastr;
use File;

class MiniAdController extends Controller
{
    public function index(Request $request)
    {
        $data = MiniAd::orderBy('id', 'DESC')->get();
        return view('backEnd.miniad.index', compact('data'));
    }
    public function create()
    {
        return view('backEnd.miniad.create');
    }
    public function store(Request $request)
    {
        $this->validate($request, [
            'title'  => 'required|string|max:255',
            'link'   => 'required|max:255',
            'status' => 'required',
            'image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:20120',
        ]);

        $data = [
            'title'  => $request->title,
            'link'   => $request->link,
            'status' => $request->status ? 1 : 0,
            'image'  => null,
        ];

        if ($request->hasFile('image')) {
            try {
                $image = $request->file('image');
                $fileName = 'miniads/' . time() . '-' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.webp';

                // Image Optimization
                $img = Image::make($image->getRealPath())->orientate();
                $encodedImage = $img->encode('webp', 80);

                // GCS Connection
                $bucket = $this->getGcsBucket();

                // Upload to GCS
                $object = $bucket->upload($encodedImage->getEncoded(), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => 'image/webp']
                ]);

                if ($object) {
                    $data['image'] = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName;
                }
            } catch (\Exception $e) {
                \Log::error("GCS Store Error: " . $e->getMessage());
                Toastr::error('Image upload failed');
                return redirect()->back();
            }
        }

        MiniAd::create($data);
        Toastr::success('Success', 'Data inserted successfully');
        return redirect()->route('miniads.index');
    }

    public function edit($id)
    {
        $edit_data = MiniAd::find($id);
        return view('backEnd.miniad.edit', compact('edit_data'));
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'link'  => 'required',
        ]);

        $update_data = MiniAd::find($request->id);
        $input = $request->all();
        $input['status'] = $request->status ? 1 : 0;

        if ($request->hasFile('image')) {
            try {
                $image = $request->file('image');
                $fileName = 'miniads/' . time() . '-' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.webp';

                // Image Optimization
                $img = Image::make($image->getRealPath())->orientate();
                $encodedImage = $img->encode('webp', 80);

                $bucketName = config('filesystems.disks.gcs.bucket');
                $bucket = $this->getGcsBucket();

                // --- পুরনো ইমেজ ডিলিট করার শক্তিশালী লজিক ---
                if ($update_data->image) {
                    try {
                        // URL থেকে পাথটি বের করা (যেমন: /nabaax-media-bucket-2/miniads/old.webp)
                        $fullPath = parse_url($update_data->image, PHP_URL_PATH);
                        
                        /** * পাথের ভেতর থেকে বাকেট নাম এবং তার আগের অংশ সরিয়ে ফেলা 
                         * যাতে শুধু 'miniads/old.webp' অংশটুকু থাকে
                         */
                        $search = '/' . $bucketName . '/';
                        $relativeOldPath = "";

                        if (strpos($fullPath, $search) !== false) {
                            $relativeOldPath = explode($search, $fullPath)[1];
                        } else {
                            // যদি বাকেট নাম পাথে না থাকে, তবে শুরুর স্লাশ ফেলে ট্রাই করা
                            $relativeOldPath = ltrim($fullPath, '/');
                        }

                        if (!empty($relativeOldPath)) {
                            $oldObject = $bucket->object($relativeOldPath);
                            if ($oldObject->exists()) {
                                $oldObject->delete();
                            }
                        }
                    } catch (\Exception $de) {
                        \Log::warning("GCS Delete Fail: " . $de->getMessage());
                    }
                }

                // নতুন ইমেজ আপলোড
                $object = $bucket->upload($encodedImage->getEncoded(), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => 'image/webp']
                ]);

                if ($object) {
                    $input['image'] = "https://storage.googleapis.com/" . $bucketName . "/" . $fileName;
                }
            } catch (\Exception $e) {
                \Log::error("GCS Update Error: " . $e->getMessage());
                Toastr::error('Update failed');
                return redirect()->back();
            }
        } else {
            $input['image'] = $update_data->image;
        }

        $update_data->update($input);
        Toastr::success('Success', 'Data updated successfully');
        return redirect()->route('miniads.index');
    }

    public function inactive(Request $request)
    {
        $inactive = MiniAd::find($request->hidden_id);
        $inactive->status = 0;
        $inactive->save();
        Toastr::success('Success', 'Data inactive successfully');
        return redirect()->back();
    }
    public function active(Request $request)
    {
        $active = MiniAd::find($request->hidden_id);
        $active->status = 1;
        $active->save();
        Toastr::success('Success', 'Data active successfully');
        return redirect()->back();
    }
    public function destroy(Request $request)
    {
        $delete_data = MiniAd::find($request->hidden_id);
        
        // ডিলিট করার সময় বাকেট থেকেও ইমেজ ডিলিট করা
        if ($delete_data && $delete_data->image) {
            try {
                $bucketName = config('filesystems.disks.gcs.bucket');
                $bucket = $this->getGcsBucket();
                $oldPath = parse_url($delete_data->image, PHP_URL_PATH);
                $relativeOldPath = ltrim($oldPath, '/' . $bucketName . '/');
                $oldObject = $bucket->object($relativeOldPath);
                if ($oldObject->exists()) {
                    $oldObject->delete();
                }
            } catch (\Exception $e) {
                \Log::error("GCS Delete Error: " . $e->getMessage());
            }
        }

        $delete_data->delete();
        Toastr::success('Success', 'Data deleted successfully');
        return redirect()->back();
    }


    private function getGcsBucket()
    {
        $keyFileData = config('filesystems.disks.gcs.key_file');
        if (!is_array($keyFileData)) {
            $keyFileData = json_decode(file_get_contents(base_path($keyFileData)), true);
        }
        
        $storage = new StorageClient([
            'projectId' => config('filesystems.disks.gcs.project_id'),
            'keyFile' => $keyFileData,
        ]);

        return $storage->bucket(config('filesystems.disks.gcs.bucket'));
    }






}

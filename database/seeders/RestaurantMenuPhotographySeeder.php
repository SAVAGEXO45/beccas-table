<?php

namespace Database\Seeders;

use App\Models\{Meal, Photo};
use App\Services\PhotoStorage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class RestaurantMenuPhotographySeeder extends Seeder
{
    public function run(PhotoStorage $storage): void
    {
        $examples = [
            'party-jollof-rice' => [
                ['jollof-plantain', 'Jollof rice and fried plantain', 'Studio Saiz', 17952746],
                ['jollof-grill', 'Nigerian jollof rice beside roasted fish and skewers', 'Keesha’s Kitchen', 13915043],
            ],
            'chicken-alfredo-pasta' => [
                ['chicken-pasta', 'Creamy chicken and mushroom fettuccine', 'Muhammad Fawdy', 13294540],
                ['chicken-pasta-detail', 'Creamy chicken Alfredo fettuccine on a white plate', 'Kemal Can', 37336685],
            ],
            'creamy-mushroom-pasta' => [
                ['mushroom-pasta', 'Creamy mushroom pasta on a dark plate', 'Shameel Mukkath', 14930718],
            ],
            'chocolate-brownie' => [
                ['brownie', 'Chocolate brownie on a white plate', 'Alesia Kozik', 6072096],
                ['brownie-detail', 'Stacked chocolate brownies with raspberries', 'Ella Olsson', 3026804],
            ],
            'lemonade' => [
                ['lemonade', 'A glass of lemonade with a slice of lemon', 'César Guel', 6411492],
            ],
        ];

        foreach ($examples as $slug => $photos) {
            $meal = Meal::where('slug', $slug)->where('price_confirmed', false)->first();
            if (!$meal || $meal->photo_id || $meal->photos()->exists()) {
                continue;
            }
            $prepared = [];
            try {
                foreach ($photos as [$fileName, $alt, $author, $id]) {
                    $path = __DIR__.'/menu-photos/'.$fileName.'.jpg';
                    // These files are reviewed, bundled assets. The import still decodes and re-encodes each JPEG.
                    $file = new class($path, $fileName.'.jpg', 'image/jpeg', UPLOAD_ERR_OK, true) extends UploadedFile {
                        public function getMimeType(): ?string
                        {
                            return getimagesize($this->getPathname())['mime'] ?? null;
                        }
                    };
                    $prepared[] = $storage->store($file) + [
                        'alt' => $alt, 'illustrative' => true,
                        'credit' => "$author · https://www.pexels.com/photo/$id/",
                    ];
                }
                $attached = DB::transaction(function () use ($meal, $prepared) {
                    $meal = Meal::lockForUpdate()->findOrFail($meal->id);
                    if ($meal->photo_id || $meal->photos()->exists()) {
                        return false;
                    }
                    foreach ($prepared as $index => $data) {
                        $photo = Photo::create($data);
                        $meal->photos()->attach($photo->id, ['position' => $index + 1]);
                        if ($index === 0) {
                            $meal->update(['photo_id' => $photo->id]);
                        }
                    }

                    return true;
                }, 3);
            } catch (Throwable $error) {
                foreach ($prepared as $item) {
                    $storage->delete($item['path']);
                }
                throw $error;
            }
            if (!$attached) {
                foreach ($prepared as $item) {
                    $storage->delete($item['path']);
                }
            }
        }
    }
}

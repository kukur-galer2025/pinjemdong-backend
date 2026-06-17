use App\Models\Product;
use App\Models\ProductImage;

\ = Product::all();
foreach (\ as \) {
    \ = 'products/' . \->slug . '.png';
    ProductImage::updateOrCreate(
        ['product_id' => \->id, 'is_primary' => true],
        ['image_path' => \, 'sort_order' => 1]
    );
}
echo 'Success';

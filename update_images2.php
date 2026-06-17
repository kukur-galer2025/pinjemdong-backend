use App\Models\Product;
use App\Models\ProductImage;

$products = Product::all();
foreach ($products as $prod) {
    $imageName = '/storage/products/' . $prod->slug . '.png';
    ProductImage::updateOrCreate(
        ['product_id' => $prod->id, 'is_primary' => true],
        ['image_path' => $imageName, 'sort_order' => 1]
    );
}
echo "Success\n";

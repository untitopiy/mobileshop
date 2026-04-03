<?php
/**
 * mobileshop/pages/cart/CartItemDTO.php
 * Data Transfer Object для элемента корзины
 */

class CartItemDTO {
    public string $cartKey;
    public int $productId;
    public ?int $variationId;
    public int $quantity;
    public int $stock;
    public string $name;
    public string $brand;
    public ?string $model;
    public ?string $sku;
    public float $currentPrice;
    public ?float $originalPrice;
    public float $subtotal;
    public ?string $imageUrl;
    public ?string $sellerName;
    public ?int $sellerId;
    public ?float $discountPercent;
    public ?string $promotionName;
    public string $type;
    public int $categoryId;
    
    public function __construct(array $data = []) {
        $this->cartKey = $data['cart_key'] ?? '';
        $this->productId = $data['product_id'] ?? 0;
        $this->variationId = $data['variation_id'] ?? null;
        $this->quantity = $data['quantity'] ?? 0;
        $this->stock = $data['stock'] ?? 0;
        $this->name = $data['name'] ?? '';
        $this->brand = $data['brand'] ?? '';
        $this->model = $data['model'] ?? null;
        $this->sku = $data['sku'] ?? null;
        $this->currentPrice = $data['current_price'] ?? 0.0;
        $this->originalPrice = $data['original_price'] ?? null;
        $this->subtotal = $data['subtotal'] ?? 0.0;
        $this->imageUrl = $data['image_url'] ?? null;
        $this->sellerName = $data['seller_name'] ?? null;
        $this->sellerId = $data['seller_id'] ?? null;
        $this->discountPercent = $data['discount_percent'] ?? null;
        $this->promotionName = $data['promotion_name'] ?? null;
        $this->type = $data['type'] ?? 'simple';
        $this->categoryId = $data['category_id'] ?? 0;
    }
    
    public static function fromDatabase(array $row): self {
        $dto = new self();
        $dto->cartKey = $row['variation_id'] 
            ? "{$row['product_id']}_{$row['variation_id']}" 
            : (string)$row['product_id'];
        $dto->productId = (int)$row['product_id'];
        $dto->variationId = $row['variation_id'] ? (int)$row['variation_id'] : null;
        $dto->quantity = (int)$row['quantity'];
        $dto->stock = (int)($row['stock'] ?? 0);
        $dto->name = $row['name'] ?? '';
        $dto->brand = $row['brand'] ?? '';
        $dto->model = $row['model'] ?? null;
        $dto->sku = $row['sku'] ?? null;
        $dto->currentPrice = (float)$row['current_price'];
        $dto->originalPrice = isset($row['original_price']) ? (float)$row['original_price'] : null;
        $dto->subtotal = (float)($row['subtotal'] ?? $dto->currentPrice * $dto->quantity);
        $dto->imageUrl = $row['image_url'] ?? null;
        $dto->sellerName = $row['seller_name'] ?? null;
        $dto->sellerId = $row['seller_id'] ?? null;
        $dto->discountPercent = isset($row['discount_percent']) ? (float)$row['discount_percent'] : null;
        $dto->promotionName = $row['promotion_name'] ?? null;
        $dto->type = $row['type'] ?? 'simple';
        $dto->categoryId = (int)($row['category_id'] ?? 0);
        
        return $dto;
    }
    
    public static function fromGuestData(array $itemData, string $cartKey, int $quantity): self {
        $dto = new self();
        $dto->cartKey = $cartKey;
        
        if (strpos($cartKey, '_') !== false) {
            list($pid, $vid) = explode('_', $cartKey);
            $dto->productId = (int)$pid;
            $dto->variationId = (int)$vid;
        } else {
            $dto->productId = (int)$cartKey;
            $dto->variationId = null;
        }
        
        $dto->quantity = $quantity;
        $dto->stock = (int)($itemData['stock'] ?? 0);
        $dto->name = $itemData['name'] ?? '';
        $dto->brand = $itemData['brand'] ?? '';
        $dto->model = $itemData['model'] ?? null;
        $dto->sku = $itemData['sku'] ?? null;
        $dto->currentPrice = (float)($itemData['current_price'] ?? 0);
        $dto->originalPrice = isset($itemData['original_price']) ? (float)$itemData['original_price'] : null;
        $dto->subtotal = $dto->currentPrice * $quantity;
        $dto->imageUrl = $itemData['image_url'] ?? null;
        $dto->sellerName = $itemData['seller_name'] ?? null;
        $dto->sellerId = $itemData['seller_id'] ?? null;
        $dto->discountPercent = isset($itemData['discount_percent']) ? (float)$itemData['discount_percent'] : null;
        $dto->promotionName = $itemData['promotion_name'] ?? null;
        $dto->type = $itemData['type'] ?? 'simple';
        $dto->categoryId = (int)($itemData['category_id'] ?? 0);
        
        return $dto;
    }
    
    public function toArray(): array {
        return [
            'cart_key' => $this->cartKey,
            'product_id' => $this->productId,
            'variation_id' => $this->variationId,
            'quantity' => $this->quantity,
            'stock' => $this->stock,
            'name' => $this->name,
            'brand' => $this->brand,
            'model' => $this->model,
            'sku' => $this->sku,
            'current_price' => $this->currentPrice,
            'original_price' => $this->originalPrice,
            'subtotal' => $this->subtotal,
            'image_url' => $this->imageUrl,
            'seller_name' => $this->sellerName,
            'seller_id' => $this->sellerId,
            'discount_percent' => $this->discountPercent,
            'promotion_name' => $this->promotionName,
            'type' => $this->type,
            'category_id' => $this->categoryId
        ];
    }
}
<?php

namespace App\Repositories;

use App\Models\SiesaWarehouseMapping;
use Illuminate\Database\Eloquent\Collection;

class SiesaWarehouseMappingRepository
{
  public function findByShopifyLocationId(int $locationId): ?SiesaWarehouseMapping
  {
    return SiesaWarehouseMapping::where('shopify_location_id', $locationId)->first();
  }

  public function all(): Collection
  {
    return SiesaWarehouseMapping::orderBy('shopify_location_name')->get();
  }

  public function create(array $data): SiesaWarehouseMapping
  {
    return SiesaWarehouseMapping::create($data);
  }

  public function update(SiesaWarehouseMapping $mapping, array $data): bool
  {
    return $mapping->update($data);
  }

  public function delete(SiesaWarehouseMapping $mapping): bool
  {
    return $mapping->delete();
  }

  public function exists(int $locationId, ?int $excludeId = null): bool
  {
    $query = SiesaWarehouseMapping::where('shopify_location_id', $locationId);

    if ($excludeId) {
      $query->where('id', '!=', $excludeId);
    }

    return $query->exists();
  }
}

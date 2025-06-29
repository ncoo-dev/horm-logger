<?php

namespace NcooDev\HormLogger\Http\Ressources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'url' => $this->url,
            'type' => $this->type->value,
            'method' => $this->method->value,
            'request' => $this->request,
            'response' => $this->response,
            'content' => $this->content,
            'status_code' => $this->status_code,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}

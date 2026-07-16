<?php
namespace App\Models;use Illuminate\Database\Eloquent\Attributes\Fillable;use Illuminate\Database\Eloquent\Model;
#[Fillable(['key','value','group'])]class Setting extends Model{public static function getValue(string $key,mixed $default=null):mixed{$value=static::query()->where('key',$key)->value('value');return $value===null?$default:$value;}public static function put(string $key,mixed $value,string $group):void{static::query()->updateOrCreate(['key'=>$key],['value'=>(string)$value,'group'=>$group]);}}

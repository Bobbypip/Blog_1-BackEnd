<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; //Pedir datos por HTTP
use Illuminate\Http\Response; //Enviar datos por HTTP
use App\Category; //Modelo de Categoria

class CategoryController extends Controller
{
    public function __construct(){
        $this->middleware('api.auth', ['except' => ['index','show']]);
    }

    public function index(){
        $categories = Category::all();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'categories' => $categories
        ]);
    }

    public function show($id){
        $category = Category::find($id);

        if (is_object($category)){
            $data = [
                'code' => 200,
                'status' => 'success',
                'category' => $category
            ];
        }else{
            $data = [
                'code' => 404,
                'status' => 'error',
                'category' => 'La categoria no existe'
            ];
        }
        return response()->json($data, $data['code']);
    }

    public function store(Request $request){
        // Recoger los datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            //validar los datos
            $validate = \Validator::make($params_array, [
                'name' => 'required'
            ]);

            // Guardar la categoria y devolver el resultado
            if($validate->fails()){
                $data = [
                    'code' => 400,
                    'status' => 'error',
                    'message' => 'No se ha guardado lo categoria'
                ];
            }else{
                $category = new Category();
                $category->name = $params_array['name'];
                $category->save(); //save() sirve para guardar la informacion en la BD, solo sirve si el modelo en este Category, ya ha sido enlazado con el ORM.

                $data = [
                    'code' => 200,
                    'status' => 'success',
                    'message' => $category
                ];
            }
        }else{
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'No has enviado ninguna categoria'
            ];
        }

        // Devolver resultado
        return response()->json($data, $data['code']);
    }

    public function update($id, Request $request){
        //Recoger datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if(!empty($params_array)){
            // Validar los datos
            $validate = \Validator::make($params_array, [
                'name' => 'required'
            ]);

            // Quitar lo que no quiero actualizar
            unset($params_array['id']);
            unset($params_array['created_at']);

            //Actualizar el registro(categoria)
                $category = Category::where('id', $id)->update($params_array);
            
            $data = [
                'code' => 200,
                'status' => 'success',
                'category' => $params_array
            ];

        }else{
            $data = [
                'code' => 400,
                'status' => 'error',
                'message' => 'No has enviado ninguna categoria.'
            ];
        }

        // Devolver respuesta
        return response()->json($data, $data['code']);
    }
}

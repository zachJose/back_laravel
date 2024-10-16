<?php

namespace App\Http\Controllers;

use App\Models\Universal;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;

class initController extends Controller
{
    protected $modelo;
    // Inyecta el modelo en el constructor
    public function __construct(Universal $modelo)
    {
        $this->modelo = $modelo;
    }

    private function nombre_tabla($palabra)
    {   //cache()->flush();
        $tables = Cache::remember('tablas', 2455555, function () {
            $connection = Schema::getConnection(); // Obtener la conexión de la base de datos
            $schemaManager = $connection->getDoctrineSchemaManager(); // Obtener el Schema Manager de Doctrine
            return $schemaManager->listTableNames();
        });
        // Buscar la tabla más similar

        $tablaMasSimilar = null;
        $distanciaMinima = PHP_INT_MAX;
        foreach ($tables as $table) {
            // Calcular la distancia de Levenshtein entre la palabra dada y el nombre de la tabla
            $distancia = levenshtein($palabra, $table);
            // Actualizar la tabla más similar si la distancia actual es menor que la mínima
            if ($distancia < $distanciaMinima) {
                if(stripos($table, $palabra)){
                    $distanciaMinima = $distancia;
                    $tablaMasSimilar = $table;
                }
            }
        }
        // Si la distancia mínima es razonablemente baja, se asume que se encontró una tabla similar
        if ($tablaMasSimilar) { // Puedes ajustar este valor según tu criterio
            return $tablaMasSimilar;
        } else {
            return "Tabla no encontrada";
        }
    }
    private function columnas_tabla($palabra)
    {
        $nombreTabla = $this->nombre_tabla($palabra);

        // Utilizar Cache::remember para almacenar en caché el resultado
        $columnas = Cache::remember("columnas_$palabra", 2455555, function () use ($nombreTabla) {
            $columnas = Schema::getColumnListing($nombreTabla);
            $tiposColumnas = [];

            // Obtener detalles de la tabla utilizando Doctrine DBAL
            $connection = Schema::getConnection();
            $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
            $detallesTabla = $doctrineSchemaManager->listTableDetails($nombreTabla);

            foreach ($columnas as $columna) {
                $tipoColumna = Schema::getColumnType($nombreTabla, $columna);
                $esRequerida = $detallesTabla->getColumn($columna)->getNotNull();
                $longitudMaxima = ($tipoColumna == 'string' || $tipoColumna == 'text') ? $detallesTabla->getColumn($columna)->getLength() : 0;

                $tiposColumnas[] = [
                    'nombre' => $columna,
                    'tipo' => $tipoColumna,
                    'es_requerida' => $esRequerida,
                    'longitud_maxima' => $longitudMaxima,
                ];
            }

            return $tiposColumnas;
        });

        return $columnas;
    }
    private function foraneas_tabla($palabra)
    {
        $clavesForaneas = [];
        $nombreTabla = $this->nombre_tabla($palabra);
        $foreignKeys =  Cache::remember("foraneas_$palabra", 2455555, function () use ($nombreTabla) {
            $connection = Schema::getConnection();
            $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
            return $doctrineSchemaManager->listTableForeignKeys($nombreTabla);;
        });
        foreach ($foreignKeys as $foreignKey) {
            $columnasLocales = $foreignKey->getLocalColumns();
            $tablaReferenciada = $foreignKey->getForeignTableName();
            $columnasReferenciadas = $foreignKey->getForeignColumns();
            $clavesForaneas[] = [
                'columnas_locales' => $columnasLocales,
                'tabla_referenciada' => $tablaReferenciada,
                'columnas_referenciadas' => $columnasReferenciadas,
            ];
        }
        return $clavesForaneas;
    }
    public function mapa_datos($cadena="mariana")
    {
        $palabra=$cadena;
        return response()->json(
            [   "tabla"=>$this->nombre_tabla($palabra),
                "columnas"=>$this->columnas_tabla($palabra),
                "foraneas"=>$this->foraneas_tabla($palabra)
            ]);
    }

    public function index()
    {
        $tables = Cache::remember('tablas', 2455555, function () {
            $connection = Schema::getConnection(); // Obtener la conexión de la base de datos
            $schemaManager = $connection->getDoctrineSchemaManager(); // Obtener el Schema Manager de Doctrine
            return $schemaManager->listTableNames();
        });
        return response()->json($tables);
    }


    public function TAblass()
    {
        return response()->json($this->columnas_tabla('municipios'));
    }

    public function show($id)
    {
        $task = $this->modelo::find($id);
        return response()->json($task);
    }
    public function store(Request $request)
    {
        $datos = $request->all();
        if (array_key_exists('id', $datos)) {
            return  response()->json($this->uppdatt($datos));
        } else {
            $datos['contrasena'] = Hash::make($datos['contrasena2']);
        }
        try {
            $request->validate([
                'email' => 'required|email|min:5|max:255',
                'usuario' => 'required|string|between:5,10',
                'contrasena' => 'required|string|min:8',
            ]);
            unset($datos['contrasena2']);
            $id = $this->modelo->insertGetId($datos);
            return response()->json([
                'message' => 'Registro Creado',
                'id' => ['id' => $id],
                'datos' => $datos
            ]);
        } catch (ValidationException $e) {
            // Manejar la excepción de validación y personalizar la respuesta de error
            return response()->json(['error' => $e->errors()], 422);
        }
    }
    protected function uppdatt($arreglo)
    {
        $id = $arreglo['id'];
        unset($arreglo['id']);
        $task = $this->modelo::find($id);
        $cantidad = 0;
        foreach ($arreglo as $key => $value) {
            if ($task[$key] != $value) {
                $task[$key] = $value;
                $cantidad++;
            }
        }
        if ($cantidad > 0) {
            $task->save();
            return [
                'message' => 'Actualizacion completa',
                'Registro' => $task
            ];
        } else {
            return [
                'message' => 'No se Realizo ninguna actualizacion',
                'Registro' => $task
            ];
        }
    }
    public function update(Request $request, $id)
    {
        $task = $this->modelo::find($id);
        $task->name = $request->input('name');
        $task->description = $request->input('description');
        $task->completed = $request->input('completed');
        $task->save();
        return response()->json([
            'message' => 'Task updated successfully',
            'task' => $task
        ]);
    }

    public function eliminar(Request $request, $id)
    {
        $datos = $request->all();
        unset($datos['id']);
        unset($datos['created_at']);
        unset($datos['updated_at']);
        $task = $this->modelo::find($id);
        $cantidad = true;
        foreach ($datos as $key => $value) {
            if ($task[$key] == $value) {
                $cantidad = $cantidad && true;
            } else {
                $cantidad = $cantidad && false;
            }
        }
        if ($cantidad) {
            $task->delete();
            return response()->json([
                'message' => 'Usuario eliminado con exito'
            ]);
        } else {
            return response()->json([
                'message' => 'Operacion Invalida'
            ]);
        }
    }

    public function destroy($id)
    {
        $task = $this->modelo::find($id);
        $task->delete();
        return response()->json([
            'message' => 'Task deleted successfully'
        ]);
    }

    public function enviarCorreo(Request $request)
    {
        try {
            $request->validate([
                'contenido' => 'required|string|between:10,1000',
                'para' => 'required|email|min:5|max:255',
                'asunto' => 'required|string|max:255',
            ]);
            $datos = $request->all();
            // Env�o del correo
            Mail::raw($datos['contenido'], function ($message) use ($datos) {
                $message->to($datos['para'])->subject($datos['asunto']);
            });
            return response()->json(['mensaje' => 'Correo enviado correctamente']);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        }
    }
}

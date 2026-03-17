<?php
class mySQLConexion
{
    //atributes
    private $host = "127.0.0.1";
    private $user = "root";
    private $password = "1amjoseph";
    private $DB = "panesBea";
    private $port = "3306";
    private $status;

    //construct
    public function __construct()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->status = new mysqli(
                $this->host,
                $this->user,
                $this->password,
                $this->DB,
                $this->port
            );
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            throw new Exception("Database connection failed");
        }
    }


    //Metodos CRUD universales para todas las tablas, uso de TryCatch para errores especificos
    //Metodo SELECT (todos los campos)
    public function SELECT(string $table)
    {
        try {
            return $this->status->query("select * from " . $table);
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    //Metodo SINGLESELECT (un registro)
    public function SINGLESELECT(string $table, string $reference, string $id)
    {
        $fixedTable = trim($table);
        try {
            return $this->status->query("select * from `$fixedTable` where `$reference` = $id");
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    //Metodo INSERT
    public function INSERT(string $table, array $fields, array $values)
    {
        if (count($fields) !== count($values)) {
            throw new Exception("fields & values don't match");
        }

        $fixedTable = trim($table);
        $fixedFields = implode(", ", $fields);
        $fixedValues = array_map(function ($v) {
            return "'" . $this->status->real_escape_string($v) . "'";
        }, $values);
        $fixedValues = implode(", ", $fixedValues);
        try {
            return $this->status->query("insert into `$fixedTable` ($fixedFields) values ($fixedValues)");
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    public function UPDATE(string $table, string $reference, array $fields, array $values)
    {
        if (count($fields) !== count($values)) {
            throw new Exception("fields & values don't match");
        }

        $fixedTable = trim($table);
        //proteger nombres de campos
        $fields = array_map(fn($f) => "`$f`", $fields);

        //escapar valores y formar pares campo = valor
        $setParts = array_map(function ($field, $value) {
            $safeValue = $this->status->real_escape_string($value);
            return "$field = '$safeValue'";
        }, $fields, $values);

        $setClause = implode(", ", $setParts);
        try {
            $sentence = "UPDATE `$table` SET $setClause WHERE $reference";
            return $this->status->query($sentence);
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    //Metodo DELETE
    public function DELETE($table, $reference, $id)
    {
        try {
            return $this->status->query("delete from `$table` where `$reference` = $id");
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    public function DAILYSTATS()
    {
        $petition = "SELECT IFNULL(SUM(total),0) AS total_hoy FROM venta WHERE fecha = CURDATE();";

        try {
            $result = $this->status->query($petition);
            $row = $result->fetch_assoc();
            return $row['total_hoy'];
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    public function MONTHLYSTATS()
    {
        $petition = "SELECT IFNULL(SUM(total),0) AS total_mes 
                 FROM venta 
                 WHERE MONTH(fecha) = MONTH(CURDATE()) 
                 AND YEAR(fecha) = YEAR(CURDATE());";

        try {
            $result = $this->status->query($petition);
            $row = $result->fetch_assoc();
            return $row['total_mes'];
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    public function DAILYSALES()
    {
        $petition = "SELECT COUNT(*) AS ventas_hoy FROM venta WHERE fecha = CURDATE();";

        try {
            $result = $this->status->query($petition);
            $row = $result->fetch_assoc();
            return $row['ventas_hoy'];
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }


    public function TOPPRODUCT()
    {
        $petition = "SELECT p.nombre, SUM(d.cantidad) AS total_vendido
                 FROM detalle_venta d
                 JOIN producto p ON d.id_producto = p.id_producto
                 GROUP BY p.id_producto
                 ORDER BY total_vendido DESC
                 LIMIT 1;";

        try {
            $result = $this->status->query($petition);
            $row = $result->fetch_assoc();
            return $row;
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    public function GETSALES()
    {
        $petition = "SELECT * FROM venta ORDER BY id_venta desc";

        try {
            $result = $this->status->query($petition);
            return $result;
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }

    public function GETINVENTORY()
    {
        $petition = "SELECT nombre, stock, unidad_medida 
                 FROM ingrediente 
                 ORDER BY stock ASC";

        try {
            $result = $this->status->query($petition);
            return $result;
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            die($th->getMessage());
        }
    }
}

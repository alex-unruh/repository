<?php

namespace AlexUnruh\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * An abstraction database layer built around the Doctrine DBAL SQL Query Builder
 * Se more information in: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/index.html
 * 
 * @author Alexandre Unruh <alexandre@unruh.com.br>
 * @license MIT
 */
class Repository extends QueryBuilder
{

	/**
	 * Must have connection parameters to Doctrine DBAL use
	 * See more in: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#configuration
	 *
	 * @var array
	 */
	protected $connection_params = [];

	/**
	 * The name of the table in use
	 *
	 * @var string
	 */
	protected $table_name = null;

	/**
	 * The alias to the table in use for join statements
	 *
	 * @var string
	 */
	protected $table_alias = null;

	/**
	 * Array with pairs key => value to data types
	 * See more in: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html#types
	 *
	 * @var array
	 */
	protected $data_types = [];

	/**
	 * An array of key => values to use in internal data binds 
	 *
	 * @var array
	 */
	protected $statement_params = [];

	public function __construct(array $connection_params = null)
	{
		if (!$connection_params) return;
		$connection = DriverManager::getConnection($connection_params);
		parent::__construct($connection);
	}

	/**
	 * Define a pre-existing connection
	 *
	 * @param Connection $connection
	 * @return Repository
	 */
	public function setConnection(Connection $connection): Repository
	{
		parent::__construct($connection);
		return $this;
	}

	/**
	 * Execute a query return all results
	 *
	 * @return array|false $result
	 */
	public function get()
	{
		return $this->executeQuery()->fetchAllAssociative();
	}

	/**
	 * Execute a query return only the first result
	 *
	 * @return array|false $result
	 */
	public function getFirst()
	{
		return $this->executeQuery()->fetchAssociative();
	}

	/**
	 * Must be used to execute a statement initialized by update or delete methods
	 *
	 * @return integer
	 */
	public function execute(): int
	{
		$this->setParameters($this->statement_params, $this->data_types);
		$result = $this->executeStatement();
		return $result;
	}

	/**
	 * Set the types of data to be binded in bindParams
	 * Nedd to have an array with the respective pairs of key and value. eg. ['name' => 'string', 'created_at' => 'datetime', ...]
	 *
	 * @param array $types
	 * @return Repository
	 */
	public function setTypes(array $types): Repository
	{
		$this->data_types = $types;
		return $this;
	}

	/**
	 * Prepare the SQL statement in insert method
	 *
	 * @param array $data
	 * @param boolean $use_colon Keep true to use ":param" sintax or false to use "?"
	 * @return Repository
	 */
	public function addValues(array $data, $use_colon = true): Repository
	{
		foreach ($data as $key => $val) {
			$this->statement_params[$key] = $val;
			$value = $use_colon ? ":{$key}" : "?";
			$this->setValue($key, $value);
		}
		return $this;
	}

	/**
	 * Prepare the SQL statement in update method
	 * Shold not be used within updates with joins
	 *
	 * @param array $data
	 * @param boolean $use_colon Keep true to use ":param" sintax or false to use "?"
	 * @return Repository
	 */
	public function setValues(array $data, $use_colon = true): Repository
	{
		foreach ($data as $key => $val) {
			$this->statement_params[$key] = $val;
			$value = $use_colon ? ":{$key}" : "?";
			$this->set($key, $value);
		}
		return $this;
	}

	/**
	 * Select data in a database table. Shold be used only in children classes.
	 * Need's a table name defined before. 
	 *
	 * @param array $data
	 * @param string|null $table_alias
	 * @return Repository
	 */
	public function read(array $data, string $table_alias = null): Repository
	{
		$columns = implode(', ', $data);
		$this->select($columns)->from($this->table_name, $table_alias);
		return $this;
	}

	/**
	 * Create a new record in a dabatase table. Shold be used only in children classes.
	 * Need's a table name defined before.
	 *
	 * @param array $data
	 * @return Repository
	 */
	public function create(array $data): Repository
	{
		$this->insert($this->table_name)->addValues($data);
		return $this;
	}

	/**
	 * Update a set of culumns in database. Shold be used only in children classes.
	 * Need's a table name defined before. Be careful! Always use with where clauses
	 *
	 * @param array $data
	 * @param string|null $table_alias
	 * @return Repository
	 */
	public function modify(array $data, string $table_alias = null): Repository
	{
		$this->update($this->table_name, $table_alias)->setValues($data);
		return $this;
	}

	/**
	 * Delete record(s) in a database table, Shold be used only in children classes.
	 * Need's a table name defined before. Be careful! Always use with where clauses
	 *
	 * @param string|null $table_alias
	 * @return Repository
	 */
	public function destroy(string $table_alias = null): Repository
	{
		$this->delete($this->table_name, $table_alias);
		return $this;
	}
}

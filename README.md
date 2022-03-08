# Alex Unruh - Repository

This is an abstraction layer that extends [Doctrine DBAL Query Builder]('https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/query-builder.html#sql-query-builder) with methods to help and reduce the amount of code for bind values, for example.

Let's see below a scenario with an insert query using only the Doctrine DBAL QueryBuilder:

```php
// index.php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;

$conn = DriverManager::getConnection($connection_params);
$query_builder = $conn->createQueryBuilder();

$query_builder->insert('users')
    ->setValue('Name', '?')
    ->setValue('CountryCode', '?')
    ->setValue('District', '?')
    ->setValue('Population', '?')
    ->setParameter(0, 'Osasco')
    ->setParameter(1, 'BRA')
    ->setParameter(2, 'São Paulo')
    ->setParameter(3, '800000')
    ->executeStatement();
```
Now let's see the same query with a bit of abstraction using Repository::class

```php
// index.php
use AlexUnruh\Repository;

$repo = new Repository($connection_params);
$repo->insert('city')
    ->addvalues(['Name' => 'Osasco', 'CountryCode' => 'BRA', 'District' => 'São Paulo', 'Population' => '800000'])
    ->execute();
```
Behind the scenes, the repository class executes exactly the same procedure, making the binds safely and returning the same result. If you has a big table, this can be very useful.
In fact, all the methods present in Doctrine DBAL Query Builder be present in Repository::class because, as we already said, it extends DBAL QB. You're free to use as you want.

## Methods that don't exist in the parent class (Doctrine DBAL QueryBuilder)

### setConnection( array $connection_params ): Repository

If you need to use the same coonection in multiple queries, like a transaction, this is very useful:
```php
//index.php

use AlexUnruh\Repository;

$conn = DriverManager::getConnection($connection_params);
$conn->transactional({
  $repo = new Repository();
  $repo->setConnection($conn);
  
  $repo->select('author_id')->from('posts')->where('slug = :slug')->setParameter('slug', 'my-post');
  $result = $repo->getFirst();
  $id = $result['author_id'];

  $repo->resetQueryParts();
  $repo->update('users')->setValues(['best_post' => true])->where("id = {$id}")->execute();
});
```
### get()

Used in final of a select statement to return a multidimensional array
```php
// index.php
use AlexUnruh\Repository;

$repo = new Repository($connection_params);
$repo->select('*')->from('users')->get();

// Returns
/*
 [
  [
    'id' => '1',
    'name' => 'Foo',
    'username' => 'Bar,
    'password' => 'foobar'
  ],
  [
    'id' => '2',
    'name' => 'Bar',
    'username' => 'Foo,
    'password' => 'barfoo'
  ],
 ]
*/
```
### getFirst()

Used in final of a select statement to return only the first result of a query
```php
// index.php
use AlexUnruh\Repository;

$repo = new Repository($connection_params);
$repo->select('*')->from('users')->getFirst();

// Returns
/*
  [
    'id' => '1',
    'name' => 'Foo',
    'username' => 'Bar,
    'password' => 'foobar'
  ]
*/
```
### addValues( array $array_data )
- @param array $array_data = An array containing pairs of key => value to be inserted in the table
```php
// index.php
use AlexUnruh\Repository;

$repo = new Repository($connection_params);
$repo->insert('users')->addValues(['name' => 'Foo', 'email' => 'foo@bar.com', 'pass' => $encripted_pass])->execute();
```
### setValues( array $array_data )
- @param array $array_data = An array containing pairs of key => value to be updated in the table
```php
// index.php
use AlexUnruh\Repository;

$repo = new Repository($connection_params);
$repo->update('users')->setValues(['name' => 'Foo', 'email' => 'foo@bar.com', 'pass' => $encripted_pass])->execute();
```
### execute()
The execute method is responsible to make the safely bind params in insert, update and delete statements and return as the result the number of rows affected. It calls the setParameters() and executeStatement() methods by Doctrine DBAL parent class. 
```php
// index.php 
use AlexUnruh\Repository;

$repo = new Repository($connection_params);
$repo->update('users')->setvalues(['name' => 'New Name'])->execute();
```
Although it is not visible, there will be a bindParam between the setValues and execute methods

### setTypes( array $types ) : Repository
- @param array $types = An artray with pairs of key => values to be used in queries that you need to specify the type of the data to be inserted or updated. See more in [Doctrine DBAL Docs]('https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html#types')

In some cases, when you work with Doctrine DBAL or other Query Builders, is necessary to set a data type of a specific column data. If you are store a cripted data in a table, for example, maybe was necessary to tell to Query Builder whats the type of this data because each type of database stores this differently.

In other cases, for security, you need to set the type of a value before stores him in your table, in setParameter method. If the type is different from the defined, an Exception will be thrown.

Lets see an example with Doctrine DBAL QueryBuilder:
```php
// index.php 
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;

$conn = DriverManager::getConnection($connection_params);
$query_builder = $conn->createQueryBuilder();

$query_builder->insert('posts')
    ->setValue('name', ?)
    ->setValue('slug', ?)
    ->setValue('author_id', ?)
    ->setValue('image', ?)
    ->setParameter(0, $post_name, 'string')
    ->setparameter(1, $slug, 'string')
    ->setparameter(2, $author_id, 'integer')
    ->setparameter(3, $encrypted_data, 'blob')
    ->executeSatetment();
  ```
  Now, let's see with Repository::class
  ```php
// index.php 
use AlexUnruh\Repository;

$repo = new Repository($connection_params);
$repo->setTypes(['name' => 'string', 'slug' => 'string', 'author_id' => 'string', 'image' => 'blob']);
$repo->insert('posts')
    ->addValues(['name' => $post_name, 'slug' => $slug, 'author_id' => $author_id. 'image' => $encripted_data])
    ->execute();
```

## Extending the Repository::class:

Most queries can be performed using methods from the parent Repository class. But in some cases you may have more complex queries (like queries containing subqueries), which would "bloat" your controllers and you would like to put these queries in a separate class, using a repository pattern. For that you can create your own class and extend the repository parent class.

All classes that extends the Repositor::class needs to have at least the protected $table_name property to use the special crud methods that will be described below.

Another parameter that can be implemented in an inheritor class is $data_types which contains the data types described in the setTypes method above.

Don't waste time trying to understand the method below. While it works, it's just here to demonstrate a Repository pattern use case. 
```php
// MyRepo.php
use AlexUnruh\Repository;

class MyRepo extends Repository
{
  protected $table_name = 'images';
  protected $data_types = [];

  /**
  * Remember, we are extending the Query Builder class, so we use "$this" here
  */
  public function lockRecord(int $status)
  {
    $id = uniqid(rand(), true);
    $now = date('Y-m-d H:i:s');
    $max_time = date('Y-m-d H:i:s', strtotime('+5 minutes', strtotime($now)));

    $subquery = $this->select('uuid')
      ->distinct()
      ->from('another_table')
      ->where('status = ?')
      ->setMaxResults(1)
      ->getSQL();

    $this->resetQueryParts();
    $this->modify(['time_lock' => "'$max_time'", 'id_lock' => $id])
      ->where("id_lock = 0 OR time_lock < '{$now}'")
      ->andwhere('cancel = 0')
      ->andWhere('status = ?')
      ->andWhere("uuid IN ($subquery)")
      ->setParameter(0, $status)
      ->setParameter(1, $status);

    return $this->execute() ? true : false;
  }
}

// index.php
$repo = new MyRepo($connection_params);
$repo->lockRecord();
```
## Methods available to be used only in extended classes: read, create, modify and remove.
All the methods described bellow neede to be used in clases that extends the Repositor::class because they use parameters defined in this classes, as $table_name or $data_types, for example.

### read( array $data, string $table_alias = null ): Repository
- @param array $array_data = An array containing the data to be selected from the table
- @param string $table_alias = A table alias to be used in join statements (optional)

```php
// index.php

// example 1
$user = new UserRepo($connection_params);
$result = $user->read(['name', 'username'])->where('id' => 5)->getFirst();

// example 2 (With table alias an join)
$user = new UserRepo($connection_params);

// second parameter "a" is the table alias to users table
$user->read(['a.name as author', 'b.*'], 'a')
    ->join('a', 'posts', 'b', 'b.author_id' = 'a.id')
    ->where('a.id = :id')
    ->setparameter('id', $id);

$result = $user->get();
```
### create(array $data): Repository
- @param array $array_data = An array containing pairs of key => value to be inserted in the table

Behind the scenes, repository::class wiil make the safely bind values using the $data_types array to each column if it be present on class properties.
```php
// index.php
$user = new UserRepo($connection_params);
$user->create(['name' => 'Foo', 'email' => 'foo@bar.com', 'pass' => $encripted_pass])->execute();
```
### modify( array $data, string $table_alias = null ): Repository
- @param array $array_data = An array containing pairs of key => value to be updated in the table
- @param string $table_alias = A table alias to be used in join statements (optional)

Behind the scenes, repository::class wiil make the safely bind values using the $data_types array to each column if it be present on class properties. Always use with where clauses
```php
//index.php 
$user = new UserRepo($connection_params);
$user->modify(['name' => 'Foo', 'email' => 'foo@bar.com', 'pass' => $encripted_pass])->where('id = ?')->setParameter('id', $id)->execute();
```
### destroy( string $table_alias = null ): Repository
- @param string $table_alias = A table alias to be used in join statements (optional)
```php
// index.php
$user = new UserRepo($connection_params);
$user->destroy()->where('id = :id')->setParameter('id', $id, 'integer')->execute();
```
Enjoy.
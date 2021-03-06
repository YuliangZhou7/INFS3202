 <?php
class RecipesModel {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Insert a new recipe into database.
     * Performs 3 insert operations on 'recipe', 'ingredients', and
     * 'directions' tables.
     *
     * @param: $data associate array containing all recipe information to upload
     *
     * @return: true if recipe successfully added, false otherwise
     */
    public function createNewRecipe($data) {
        // Get id of new recipe
        $this->db->query('SELECT rid FROM recipes WHERE rid=(SELECT MAX(rid) FROM recipes)');
        $this->db->execute();
        $row = $this->db->single();
        // Increment recipe id
        $newRecipeID = $row->rid + 1;

        // Upload image and return path
        $imgPath = $this->uploadImage($newRecipeID, $data['uid'], $data['img']);

        // Prepare sql query for new recipe entry
        $this->db->query('INSERT INTO recipes (rid,title,ownerid,description,prepTime,servingSize,imagePath,link) VALUES(:rid,:title,:uid,:description,:prepTime,:servingSize,:imagePath,:link);');
        // Bind values for prepared statement
        $this->db->bind(':rid', $newRecipeID);
        $this->db->bind(':uid', $data['uid']);
        $this->db->bind(':title', $data['title']);
        $this->db->bind(':description', $data['description']);
        $this->db->bind(':prepTime', $data['prepTime']);
        $this->db->bind(':servingSize', $data['servingSize']);
        $this->db->bind(':imagePath', $imgPath);
        $this->db->bind(':link', $data['link']);
        // Execute query
        try {
            $this->db->execute();
        } catch (PDOException $e) {
            echo '</br>FAILED recipe: ' . $e->getMessage() . '</br>';
            return false;
        }

        // Insert ingredients
        $ingredQueryArray = $this->convertIngredientsFormat($newRecipeID, $data['ingredients']);
        $ingredQueryString = 'INSERT INTO ingredients (rid,ingredientid,value) VALUES ' . implode(", ", $ingredQueryArray) . ';';
        // echo $ingredQueryString;
        $this->db->query($ingredQueryString);
        // Bind all values in ingredients query
        for($i = 0; $i < count($ingredQueryArray); $i++) {
            $this->db->bind(':value' . ($i + 1), $data['ingredients'][$i]);
        }
        // Execute query
        try {
            $this->db->execute();
        } catch (PDOException $e) {
            echo '</br>FAILED ingredients: ' . $e->getMessage() . '</br>';
            return false;
        }

        // Insert directions
        $direcQueryArray = $this->convertDirectionsFormat($newRecipeID, $data['directions']);
        $direcQueryString = 'INSERT INTO directions (rid,stepNum,description) VALUES ' . implode(", ", $direcQueryArray) . ';';
        // echo $direcQueryString;
        $this->db->query($direcQueryString);
        // Bind all values in ingredients query
        for($i = 0; $i < count($direcQueryArray); $i++) {
            $this->db->bind(':value' . ($i + 1), $data['directions'][$i]);
        }
        // Execute query
        try {
            $this->db->execute();
        } catch (PDOException $e) {
            echo '</br>FAILED directions: ' . $e->getMessage() . '</br>';
            return false;
        }

        return true;
    }

    /**
     * Updates recipe specified by rid in parameters
     * Performs 1 update for 'recipe' table, and 1 for each 'ingredients' and 1 for each 'directions'
     *
     * @param: $data associate array containing all recipe information to update
     *
     * @return: true if recipe successfully updated, false otherwise
     */
    public function updateRecipe($data) {
        // Prepare sql query for new recipe entry
        $this->db->query('UPDATE recipes SET title=:title, description=:description, prepTime=:prepTime, servingSize=:servingSize, link=:link WHERE rid=:rid;');
        // Bind values for prepared statement
        $this->db->bind(':title', $data['title']);
        $this->db->bind(':description', $data['description']);
        $this->db->bind(':prepTime', $data['prepTime']);
        $this->db->bind(':servingSize', $data['servingSize']);
        $this->db->bind(':link', $data['link']);
        $this->db->bind(':rid', $data['rid']);
        // Execute query
        try {
            $this->db->execute();
        } catch (PDOException $e) {
            echo '</br>FAILED recipe: ' . $e->getMessage() . '</br>';
            return false;
        }

        // Update Ingredients table
        for($i = 0; $i < count($data['ingredients']); $i++) {
            $this->db->query('UPDATE ingredients SET value=:value WHERE rid=:rid AND ingredientid=:iid;');
            $this->db->bind(':value', $data['ingredients'][$i]);
            $this->db->bind(':rid', $data['rid']);
            $this->db->bind(':iid', $i+1);
            $this->db->execute();
        }

        // Update Directions table
        for($i = 0; $i < count($data['directions']); $i++) {
            $this->db->query('UPDATE directions SET description=:desc WHERE rid=:rid AND stepNum=:stepNum;');
            $this->db->bind(':desc', $data['directions'][$i]);
            $this->db->bind(':rid', $data['rid']);
            $this->db->bind(':stepNum', $i+1);
            $this->db->execute();
        }

        return true;
    }

    /**
     * HELPER FUNCTION
     * Moves image from temporary location into public/img/upload/ directory
     * Image upload names given format 'r{rid}_u{uid}_preview'
     * If no image is provided (size is 0) then return placeholder img path: /img/beef.jpg
     *
     * @param: recipe id of new recipe
     * @param: user id of creator
     * @param: $_FILES['imageName']
     *
     * @return: String path to image upload location
     *          empty string on error
     */
    private function uploadImage($rid, $uid, $imgTemp) {
        if($imgTemp['size'] > 0) {
            $orginalNameExplode = explode('.', $imgTemp['name']);
            $extension = end($orginalNameExplode);
            $uploadName = 'r'.$rid.'_u'.$uid.'_preview.'.$extension;
            $uploadPath = '/upload/'.$uploadName;
            if(!file_exists($uploadPath)) {
                move_uploaded_file($imgTemp['tmp_name'], dirname(APPROOT) . '/public/img'.$uploadPath);
                return $uploadPath;
            }
        }
        // Default placeholder image path
        return '/upload/placeholder.jpg';;
    }

    /**
     * HELPER FUNCTION
     * Convert ingredients array to sql value template.
     * Each element follows format: '(rid, x, :valuex)'
     * where x increments from 1 to number of ingredients
     * To use in sql query: implode(", ", convertIngredientsFormat($rid, $data['ingredients']))
     *
     * @param: rid
     * @param: non-associative array of ingredients
     *
     * @return: array containing query values template
     */
    private function convertIngredientsFormat($rid, $ingredients) {
        $result = [];
        for($i = 1; $i < count($ingredients) + 1; $i++) {
            $result[$i - 1] = '(' . $rid . ', ' . $i . ', :value' . $i . ')';
        }
        return $result;
    }

    /**
     * HELPER FUNCTION
     * Convert directions array to sql value template.
     * Each element follows format: '(rid, x, :valuex)'
     * where x increments from 1 to number of directions
     * To use in sql query: implode(", ", convertDirectionsFormat($rid, $data['directions']))
     *
     * @param: rid
     * @param: non-associative array of directions
     *
     * @return: array containing query values template
     */
    private function convertDirectionsFormat($rid, $directions) {
        $result = [];
        for($i = 1; $i < count($directions) + 1; $i++) {
            $result[$i - 1] = '(' . $rid . ', ' . $i . ', :value' . $i . ')';
        }
        return $result;
    }

    /**
     * Retrieve recipe information from database.
     * Recipe will return object containing:
     * - title
     * - description
     * - preparation time
     * - serving size
     * - image path for preview image
     * - list of ingredients
     * - list of directions
     *
     * @param: recipe id to fetch
     * 
     * @return: all data of recipe as associative stdClass Object
     */
    public function getRecipeData($rid) {
        // Query to select recipe
        $this->db->query('SELECT * FROM recipes WHERE rid=:rid');
        $this->db->bind(':rid', $rid);
        $this->db->execute();
        $recipeResult = $this->db->single();

        // If recipe does not exist, return null
        if($recipeResult == null) {
            return null;
        }

        // Convert from object into array to append owner username, ingredients ,and directions
        $recipeResult = (array)$recipeResult;

        // Retrieve owner username and append to result
        $this->db->query('SELECT user_username FROM users WHERE user_id=:uid');
        $this->db->bind(':uid', $recipeResult['ownerid']);
        $this->db->execute();
        $result = $this->db->single();
        $recipeResult['owner_username'] = $result->user_username;

        // Query to select recipe's ingredients list
        $this->db->query('SELECT ingredientid, value FROM ingredients WHERE rid=:rid');
        $this->db->bind(':rid', $rid);
        $this->db->execute();
        $ingredientsResult = $this->db->resultSet(true);
        $recipeResult['ingredients'] = $ingredientsResult;

        // Query to select recipe's directions list
        $this->db->query('SELECT stepNum, description FROM directions WHERE rid=:rid');
        $this->db->bind(':rid', $rid);
        $this->db->execute();
        $directionsResult = $this->db->resultSet(true);
        $recipeResult['directions'] = $directionsResult;

        return (object)$recipeResult;
    }

    /**
     * Returns all recipes
     *
     * @return: associative object
     */
    public function getAllRecipes() {
        $this->db->query('SELECT * FROM recipes');
        $this->db->execute();
        return $this->db->resultSet();
    }

    /**
     * Returns recipes title and description containing keywords specified by query string
     *
     * @param: query string keyword(s)
     * 
     * @return: associative object
     */
    public function searchRecipes($query) {
        $query = '%'.$query.'%';
        $this->db->query('SELECT * FROM recipes WHERE ( title LIKE ? OR description LIKE ? )');
        $this->db->bind(1, $query);
        $this->db->bind(2, $query);
        return $this->db->resultSet();
    }

    /**
     * Returns recipes title only for keywords specified by query string
     *
     * @param: query string keyword(s)
     * 
     * @return: associative object
     */
    public function searchRecipesTitle($query) {
        $query = '%'.$query.'%';
        $this->db->query('SELECT * FROM recipes WHERE ( title LIKE ? )');
        $this->db->bind(1, $query);
        return $this->db->resultSet();
    }

    /**
     * Returns average of all comment ratings associated to rid
     *
     * @param: recipe id
     *
     * @return: average rating out of 5
     */
    public function getAverageRating($rid){
        $comments = $this->getAllComments($rid);
        $sum = 0;
        if(sizeof($comments)!=0){
            foreach($comments as $comment){
                $sum += $comment->rating;
            }
            return $sum/sizeof($comments);
        } else {
            return $sum;
        }
    }

    /**
     * Adds new comment to comments table
     *
     * @param: $data containing rid, comment, rating in an associative array
     *
     * @return: false on PDOException, comment as result
     */
    public function addNewComment($data){
        $this->db->query("INSERT INTO comments (comment_description, rating, recipe_id, ownerid) VALUES (:description, :rating, :recipeid, :ownerid)");
        $this->db->bind(':description', $data['comment']);
        $this->db->bind(':rating', $data['rating']);
        $this->db->bind(':recipeid', $data['rid']);
        $this->db->bind(':ownerid',$_SESSION['user_id']);
        try {
            $this->db->execute();
        } catch (PDOException $e) {
            return false;
        }

        // Return the comment added from the INSERT operation above
        //$this->db->query('SELECT * FROM comments WHERE comment_id = (SELECT MAX(comment_id) FROM comments)');
        $this->db->query('SELECT comments.*, users.user_username FROM comments JOIN users ON ownerid=user_id WHERE comment_id = (SELECT MAX(comment_id) FROM comments)');
        return $this->db->single();
    }

    /**
     * Returns all comments on recipe given by recipe id, will also fetch username associated to ownerid
     * 
     * @param: recipe id
     *
     * @return: associative object
     */
    public function getAllComments($rid) {
        $this->db->query('SELECT comments.*, users.user_username FROM comments JOIN users ON ownerid=user_id 
                WHERE recipe_id = :rid  ORDER BY date DESC');
        $this->db->bind(':rid', $rid);
        return $this->db->resultSet();
    }

    /**
     * TODO: dont use hard coded value
     *
     * @return: object of recipe data
     */
    public function getFeaturedRecipes(){
      $this->db->query("SELECT * FROM recipes ORDER BY rid DESC LIMIT :num");
      $this->db->bind(':num', 6);
      return $this->db->resultSet();

    }

}

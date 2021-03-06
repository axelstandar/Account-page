<?php

require_once('AccountDAL.php');
//Inkludera ControlViews för att utnyttja isset-kollerna
require_once('src/view/ControlViews.php');

/**
 * Class Users
 */
class Users {
    CONST minPostLength = 15;
    CONST minTitleLength = 4;

    private $accountDAL;

    function __construct(mysqli $db) {
        $this->accountDAL = new AccountDAL($db);
        $this->accountDAL->createAccountTable();
        $this->accountDAL->createCharacterTable();
        $this->accountDAL->createPostTable();
        $this->ctrlViews = new ControlViews();
    }

    /**
     * @param $username
     * @param $pass
     * @param string $pass2
     * @param boolean $registration
     * @return array|bool
     * @throws Exception
     */
    function checkUserNamePassword($username, $pass, $pass2="", $registration=false, $email=""){
        //Kontrollera användarnamn, använder en array för att skicka tillbaka true/false samtidigt
        //som jag skickar med ett meddelande (tom array = false, populated = true) DVS false = gå vidare / true = error
        $str = [];
        $username = strtolower($username);

        if($username == "")
            $str[0] = "Username must not be empty";
        else if(strlen($username) < 4)
            $str[0] = "Username must be at least 4 characters long";
        else if (preg_match('/[^\dA-Za-z]/i', $username))
            $str[0] = "Username must not contain symbols or characters";
        else if($pass == "")
            $str[0] = "Password must not be empty";
        else if(strlen($pass) < 6)
            $str[0] = "Password must be at least 6 characters long";
        else if($registration)
            if($pass !== $pass2)
                $str[0] = "Passwords do not match";
            else if (!preg_match("/[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+\.[a-zA-Z]{2,4}/", $email))
                $str[0] = "Invalid e-mail address";

        if(!$str) {
            if(!$registration)
                $sqlResp = $this->accountDAL->accountExists(["username" => $username], $pass);
            else
                $sqlResp = $this->accountDAL->accountExists(["username" => $username]);
            if ($sqlResp === true) {
                if (!$registration)
                    return false;
                else {
                    $str[0] = "Account already exists";
                    return $str;
                }
            }
            else {
                if($pass2 ==="" )
                    $str[0] = "Wrong username or password";
//                    $str[0] = "Could not find username/password in database(debugging)";
                return $str;
            }
        }else{
            if($pass2!=="" || $registration)
                return $str;
            if($username!== "" && $pass !=="")
                $str[0] = "Wrong username or password";
            return $str;
        }
    }

    /**
     * @return bool
     */
    function logOn(){
//        if(isset($_POST["login_username"]) && isset($_POST["login_password"])) {
            $resp = $this->checkUserNamePassword($_POST["login_username"], $_POST["login_password"]);
            if (!$resp) {
                //set cookie username, and a token(random data) to currsessionid plus time right now (201411261412)-format
                $now = date("YmdGis");
                $token = password_hash(session_id().$now, PASSWORD_BCRYPT);
                $this->accountDAL->createAccAuth($_POST["login_username"], $token, $now);
                setcookie("UID", $_POST["login_username"], time()+60*60*24*365, '/');
                setcookie("SID", $token, time()+60*60*24*365, '/');
                $_SESSION["logged_in"] = true;
            }
            else {
                return $resp[0];
            }
//        }
        return false;
    }

    /**
     * @return bool
     */
    function checkLoggedIn(){
        if(isset($_COOKIE["UID"]) && isset($_COOKIE["SID"])){
            if($this->accountDAL->authLogin([
                "username"=>$_COOKIE["UID"],
                "loginid"=>$_COOKIE["SID"]
            ])){
                $_SESSION["logged_in"] = true;
                return true;
            }
        }
        return false;
    }

    /**
     * @param $usr
     * @param $pass1
     * @param $pass2
     * @param $email
     * @return bool
     * @throws Exception
     */
    function createNewAccount($usr, $pass1, $pass2, $email){
        $str = $this->checkUserNamePassword($usr, $pass1, $pass2, true, $email);
        if(!$str)
            return $this->accountDAL->createAccount([
                        "username"=>$usr,
                        "password"=>$pass1,
                        "email"=>$email]);
        return $str[0];
    }

    function existingCharacter($playername){
        $str = [];
        $username = strtolower($playername);

        if($username == "")
            $str[0] = "Username must not be empty";
        else if(strlen($username) < 4)
            $str[0] = "Username must be at least 4 characters long";
        else if (preg_match('/[^\dA-Za-z]/i', $playername))
            $str[0] = "Username must not contain symbols or characters";
        if(!$str) {
            if($this->accountDAL->characterExists(["playername" => $playername])){
                $str[0] = "Character already exists";
                return $str;
            }else{
                return false;
            }
        }
        return $str;
    }

    function retrieveAccount($usr){
        return $this->accountDAL->getAccount(["username"=>$usr]);
    }

    function retrievePosts(){
        return $this->accountDAL->getPosts();
    }

    function getCharacterListWithId($id){
        return $this->accountDAL->getCharactersWithAccountId(["account_id"=>$id]);
    }

    function getCharacterListWithUsername($usr){
        $id = $this->getAccountId($usr);
        return $this->accountDAL->getCharactersWithAccountId(["account_id"=>$id]);
    }

    function getAccountId($usr){
        $accInfo = $this->accountDAL->getAccount(["username"=>$usr]);
        return $accInfo["id"];
    }

    function deleteCharacter($playername){
        return $this->accountDAL->deleteCharacter(["playername"=>$playername]);//
    }

    function createNewCharacter($characterInfo){
        $ret = $this->existingCharacter($characterInfo["playername"]);
           if(!$ret)
               return $this->accountDAL->createCharacter($characterInfo);
        return $ret[0];
    }

    function validatePost($postTitle, $postText){
        $str = [];
        if(strlen($postTitle)<self::minTitleLength)
            return $str[0] = "Title must be longer than  ".self::minTitleLength." characters";
        else if(strlen($postText)<self::minPostLength)
            return $str[0] = "Body of post must be at least ".self::minPostLength." characters";
        return false;
    }

    function createNewPost($post){
        $ret = $this->validatePost($post["title"], $post["text"]);
        if(!$ret)
            return $this->accountDAL->createPost($post);
        return $ret;
    }

    function editExistingPost($post){
        $ret = $this->validatePost($post["title"], $post["text"]);
        if(!$ret)
            return $this->accountDAL->editPost($post);
        return $ret;
    }

    function deletePost($postid){
        return $this->accountDAL->deletePostDB($postid);
    }

}
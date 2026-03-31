<?php
/**
* Password.class.php
*
* Tools for hashing passwords and testing password hashes.
*
*/
class Password
{
    private $appkey;

    public function __construct(string $appkey)
    {
        $this->appkey = $appkey;
    }

    public function createPasswordHash(string $password): array
    {
    
        $peppered = $this->appkey . $password;

        $hash = password_hash($peppered, PASSWORD_DEFAULT, ['cost' => 13]);

        return [
            'hash' => $hash,
            'salt' => '', // legacy stuff so code doesn't broke for now, password_has has built in salt
        ];
    }

    
    public function testPassword(string $testPassword, string $passwordHash, string $salt = ''): bool
    {
        $peppered = $this -> appkey . $testPassword;
        return password_verify($peppered, $passwordHash);
    }
}
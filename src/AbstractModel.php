<?php


class AbstractModel
{
    function __construct(string $jsonPayload)
    {
        $data = json_decode($jsonPayload, true);
        foreach ($data as $key => $value) $this->{$key} = $value;
    }
}

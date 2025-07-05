<?php

namespace Sabatier\Service;

abstract class JSONWebTokenCoderStrategy
{
    public static JSONWebTokenSigningAlgorithm $algorithm = JSONWebTokenSigningAlgorithm::none;
}

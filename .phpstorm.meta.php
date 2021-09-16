<?php

namespace PHPSTORM_META {

    use Noem\Container\Container;

    override(Container::get(),map([
        '' => '@'
    ]));
}

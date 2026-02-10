<?php

echo "y" | passthru('php bin/console doctrine:migrations:migrate');

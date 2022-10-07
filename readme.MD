# Toolbox Timber Blocks

Register Gutenberg Blocks by adding a Timber/Twig template in your theme, plugin or Toolbox Twig Templates CPT.

## Filters And Hooks

|filtername|args|what it does|
|---|---|---|
|`toolbox/twig-template-dirs`|`$directories (array)`|array of directories to start looking for twig templates|
|`timber/acf-gutenberg-blocks-data`|`$context (array)`|Generic filter to extend the data used in the twig render|
|`timber/acf-gutenberg-blocks-data/{$slug}`|`$context (array)`|Filter to extend the data used in twig render, targeted to block slug|
|`timber/acf-gutenberg-blocks-data/{$id}`|`$context (array)`|Filter to extend the data used in twig render, targeted to block id at runtime|
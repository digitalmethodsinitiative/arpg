arpg
====

Amazon Related Product Graph

This script allows you to enter a (set of) ASIN(s) and crawl its recommendations up til a user-specified depth. The output is a GEXF file with the recommendation network for those ASINs.

Usage
====

Change the settings in config.php and run the script as follows: php amazon_related_product_graph.php

Dependencies
====

The git clone has git subtrees for https://github.com/Exeu/apai-io/ and https://github.com/digitalmethodsinitiative/GEXF-library so you should be good to go.

If you want to update those dependencies, do the following:

git subtree pull --prefix ApaiIO git@github.com:Exeu/apai-io.git master --squash

git subtree pull --prefix Gexf git@github.com:digitalmethodsinitiative/GEXF-library.git master --squash
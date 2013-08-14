<?php

/*
 * This script uses Amazon's Product Advertising API to retrieve related items from Amazon ECS API
 * Can be used to get e.g. related books network starting from one, or a set of, ASINs.
 * @see http://docs.aws.amazon.com/AWSECommerceService/2011-08-01/DG/SimilarityLookup.html
 *
 * Modify below variables and run the script from the command line: php amazon_related_item_explorer.php
 *
 * This script or any part of it should not be copied or distributed. It is intended for academic use only. 
 *
 * @author Erik Borra <erik@digitalmethods.net>
 *
 */

require_once 'config.php';
require_once 'Gexf/Gexf.class.php';

/*
 *  things needed for ApaiIO
 */
require_once 'ApaiIO/tests/UniversalClassLoader.php';
$classLoader = new \Symfony\Component\ClassLoader\UniversalClassLoader();
$classLoader->registerNamespace('ApaiIO', 'ApaiIO/lib');
$classLoader->register();

use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\ApaiIO;
use ApaiIO\Operations\Lookup;
use ApaiIO\Operations\SimilarityLookup;

if (AWS_API_KEY == "")
    die("You should specify an Amazon API key\n");
if (empty($asins))
    die("You should specify at least one ASIN\n");
if (empty($locales))
    die("You should specify at least one locale\n");

/*
 * Main loop. For each language and each ASIN produce a related item graph
 */
foreach ($locales as $language) {
    if ($combined) { // start combined GEXF network
        $gexf = new Gexf();
        $gexf->setTitle("Amazon related books for ASINs " . implode(",", $asins) . " in language $language");
        $gexf->setEdgeType(GEXF_EDGE_DIRECTED);
        $gexf->setMode(GEXF_MODE_DYNAMIC);
        $gexf->setTimeFormat(GEXF_TIMEFORMAT_DATE);
        $nodes = array();
    }
    foreach ($asins as $asin) {
        if (strlen($asin) < 10)
            $asin = "0$asin";

        print "\nDoing $startFileNameWith $language $asin\n";

        // set up apaiIO
        $conf = new GenericConfiguration();
        try {
            $conf
                    ->setCountry($language)
                    ->setAccessKey(AWS_API_KEY)
                    ->setSecretKey(AWS_API_SECRET_KEY)
                    ->setAssociateTag(AWS_ASSOCIATE_TAG)
                    ->setResponseTransformer('\ApaiIO\ResponseTransformer\XmlToSimpleXmlObject')
            ;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $apaiIO = new ApaiIO($conf);

        if (!$combined) { // start individual GEXF network
            $gexf = new Gexf();
            $gexf->setTitle("Amazon related books for ASIN $asin in language $language");
            $gexf->setEdgeType(GEXF_EDGE_DIRECTED);
            $gexf->setMode(GEXF_MODE_DYNAMIC);
            $gexf->setTimeFormat(GEXF_TIMEFORMAT_DATE);
        }

        // crawl seed node
        $node1 = new GexfNode($asin);

        $lookup = new Lookup();
        $lookup->setItemId($asin);
        $lookup->setResponseGroup(array('Large'));  // http://docs.aws.amazon.com/AWSECommerceService/latest/DG/RG_Large.html
        $response = $apaiIO->runOperation($lookup);

        addNodeInfo($node1, $response->Items->Item, 0);
        $gexf->addNode($node1);

        if ($combined) { // wait so we can go breadth first over all seeds
            $nodes[$asin] = $node1;
        } else {
            // recurse
            addToGraph($asin, $node1, $gexf, $apaiIO, 0);
        }

        if (!$combined) { // render and write out GEXF file	
            $gexf->render();
            file_put_contents($startFileNameWith . "_" . $language . "_" . $asin . "_" . $crawldepth . "_" . preg_replace("/[^\d\w]/", "_", $node1->getNodeName()) . ".gexf", $gexf->gexfFile);
        }
        print "giving amazon API a break ...\n";
        sleep(5);
    }
    if ($combined) {
        foreach ($nodes as $asin => $node)
            addToGraph($asin, $node, $gexf, $apaiIO, 0);
        $gexf->render();
        file_put_contents($startFileNameWith . "_" . $language . "_COMBINED_" . $crawldepth . "_COMBINED.gexf", $gexf->gexfFile);
    }
}

/*
 * Breath first recursion through related book graph
 */

function addToGraph($asin, &$node1, &$gexf, &$apaiIO, $depth) {
    global $crawldepth;
    $similaritylookup = new SimilarityLookup();
    $similaritylookup->setItemId($asin);
    $similaritylookup->setResponseGroup(array('Large'));
    $response = $apaiIO->runOperation($similaritylookup);
    $nrItems = count($response->Items->Item);
    print $nrItems . " similar items found for $asin\n";
    if ($nrItems == 0)
        return;
    $depth++;
    $nodes = $items = array();
    if (!isset($response->Items->Item))
        return;
    foreach ($response->Items->Item as $item) {
        $node2 = new GexfNode($item->ASIN);
        addNodeInfo($node2, $item, $depth);
        $gexf->addNode($node2); // if nodeId already exists it won't be added again
        $edge_id = $gexf->addEdge($node1, $node2, 1); // if edge already exists, its weight will be increased
        $nodes[] = $node2;
        $items[] = $item;
    }
    if ($depth < $crawldepth)
        foreach ($items as $k => $item) {
            addToGraph($item->ASIN, $nodes[$k], $gexf, $apaiIO, $depth);
        }
}

/*
 * Add node and all its attributes to the network
 */

function addNodeInfo(&$node, $item, $depth) {
    if (!isset($item->ItemAttributes)) {
        print "no itemattributes found\n";
        return;
    }

    if (isset($item->ItemAttributes->Author)) {
        if (is_array($item->ItemAttributes->Author))
            $author = implode(", ", $item->ItemAttributes->Author);
        else
            $author = $item->ItemAttributes->Author;
    } elseif (isset($item->ItemAttributes->Creator)) { // @todo, now mapped to author
        if (is_array($item->ItemAttributes->Creator)) {
            $tmp = array();
            foreach ($item->ItemAttributes->Creator as $creator)
                $tmp[] = $creator->_;
            $author = implode(",", $tmp) . " (eds)";
        } else
            $author = $item->ItemAttributes->Creator->_ . " (ed)";
    } else {
        $author = "n/a";
        print "no author found\n";
    }
    print "Adding $depth: {$item->ItemAttributes->Title} by {$author}\n";
    $node->setNodeName(html_entity_decode($item->ItemAttributes->Title . " by " . $author));
    $node->addNodeAttribute("title", html_entity_decode($item->ItemAttributes->Title), "string");
    $node->addNodeAttribute("Author", $author, $type = "string");
    $node->addNodeAttribute("ASIN", $item->ASIN, "string");
    $node->addNodeAttribute("SalesRank", $item->SalesRank, $type = "integer");
    $node->addNodeAttribute("InvertedSalesRank", 1 / $item->SalesRank, $type = "float");
    $node->addNodeAttribute("Publisher", $item->ItemAttributes->Publisher, $type = "string");
    $node->addNodeAttribute("PublicationDate", strftime("%Y-%m-%d", strtotime($item->ItemAttributes->PublicationDate)), $type = "string");
    $node->addNodeSpell(strftime("%Y-%m-%d", strtotime($item->ItemAttributes->PublicationDate)), strftime("%Y-%m-%d", strtotime($item->ItemAttributes->PublicationDate)));
    $node->addNodeAttribute("ProductGroup", $item->ItemAttributes->ProductGroup, $type = "string");
    $node->addNodeAttribute("ProductTypeName", $item->ItemAttributes->ProductTypeName, $type = "string");
    if (isset($item->ItemAttributes->Languages)) {
        foreach ($item->ItemAttributes->Languages->Language as $language) {
            if ($language->Type == "Published")
                $node->addNodeAttribute('Language', (String) $language->Name, "string");
        }
    }
    $node->addNodeAttribute("ListPrice", $item->ItemAttributes->ListPrice->Amount, $type = "integer");
    $node->addNodeAttribute("FormattedPrice", $item->ItemAttributes->ListPrice->FormattedPrice, $type = "string");
    $node->addNodeAttribute("CurrencyCode", $item->ItemAttributes->ListPrice->CurrencyCode, $type = "string");
    $node->addNodeAttribute("TotalUsed", $item->OfferSummary->TotalUsed, $type = "integer");
    $node->addNodeAttribute("NumberOfPages", $item->ItemAttributes->NumberOfPages, $type = "integer");
    $node->addNodeAttribute("ISBN", $item->ItemAttributes->ISBN, $type = "string");
    $node->addNodeAttribute("image", $item->SmallImage->URL, $type = "string");
    $node->addNodeAttribute('HasReviews', $item->CustomerReviews->HasReviews, $type = "boolean");
    if ($depth == 0)
        $node->addNodeAttribute("seedNode", 1, $type = "boolean");
    else
        $node->addNodeAttribute("seedNode", 0, $type = "boolean");
    $node->addNodeAttribute('crawlDepth', $depth, $type = "integer");
}

?>
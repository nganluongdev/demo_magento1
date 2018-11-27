<?php

class Magentotutorial_Complexworld_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        $weblog2 = Mage::getModel('complexworld/eavblogpost');
        $weblog2->load(1);
        var_dump($weblog2);
    }

    public function populateEntriesAction() {
        for ($i = 0; $i < 10; $i++) {
            $weblog2 = Mage::getModel('complexworld/eavblogpost');
            $weblog2->setTitle('This is a test ' . $i);
            $weblog2->setContent('This is test content ' . $i);
            $weblog2->setDate(now());
            $weblog2->save();
        }

        echo 'Done';
    }

    public function showCollectionAction() {
        $weblog2 = Mage::getModel('complexworld/eavblogpost');
        $entries = $weblog2->getCollection()
                ->addAttributeToSelect('title')
                ->addAttributeToSelect('content');
        $entries->load();
        foreach ($entries as $entry) {            //var_dump($entry);
            // var_dump($entry->getData());
            echo '<h2>' . $entry->getTitle() . '</h2>';
            echo '<p>Date: ' . $entry->getCreatedAt() . '</p>';
            echo '<p>' . $entry->getContent() . '</p>';
        }
        echo '</br>Done</br>';
    }

}

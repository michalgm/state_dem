<?php
include_once('Graph.php');

/**
This class is a toy example to show how to extend the Graph class to create a graph setup file to load data into your graph. It creates a network with two types of nodes: "animals" and "foods".  It then defines an edgetype of "animal_to_food" which, not surprisingly, will link animals to foods. Then it creates a random set of fictional edges linking animals and foods.  (What, you didn't think Lizards eat Garlic?)  Then it must define the methods to actually return the lists of nodes, edges, and the properties for nodes and edges so that the graph can be assembled correctly. These are:
	- animals_fetchNodes()
	- foods_fetchNodes()
	- animal_to_food_fetchEdges() 
	- animals_nodeProperties()
	- foods_nodeProperties()
	- animal_to_food_edgeProperties()
*/
class DemoGraph extends Graph { 


	/** 
		Overrides the parent constructor in order to define the node and edge types and set some graph properties. First it calls the parent::__construct() method to make sure things get initialized correctly. In this case it sets the nodetypes to 'animals' and 'foods'.  It sets edgetype to 'animal_to_food', sets the max an min sizes to draw the nodes, and some default GraphViz drawing parameters for nodes and edges and the overall graph. 
	*/
	function __construct() {
		parent::__construct(); //call the parent to make sure everyting is buit correctly
			
		// gives the classes of nodes
		$this->data['nodetypes'] = array('animals', 'foods'); //FIXME - add properties for types
		
	  	// gives the classes of edges, and which nodes types they will connect
		$this->data['edgetypes'] = array( 'animal_to_food' => array('animals', 'foods'));
		
		// graph level properties
		$this->data['properties'] = array(
			//sets the scaling of the elements in the gui
			'minSize' => array('animals' => '1', 'foods' => '1', 'animal_to_food'=>'10'),
			'maxSize' => array('animals' => '3', 'foods' => '3', 'animal_to_food' =>'40'),
			'nodeNum' => 25,
			'edgeNum' => 160,
			'log_scaling' => 0

		);
		// special properties for controling layout software
		//BROKEN, USE GV PARAMS
		$this->data['graphvizProperties'] = array(
			'graph'=> array(
				'bgcolor'=>'#FFFFFF',
#				'size' => '6.52,6.52!',
				'fontsize'=>90,
				'splines'=>'true',
				'fontcolor'=>'blue'
			),
			'node'=> array('label'=> ' ', 'imagescale'=>'true','fixedsize'=>1, 'style'=> 'setlinewidth(7), filled', 'regular'=>'true', 'fontsize'=>50),
			'edge'=>array('arrowhead'=>'normal', 'color'=>'#99339966', 'fontsize'=>50)
		);
		srand(20); //Don't copy this - this just makes sure that we are generating the same 'random' values each time
	}

	/**
	Will return a set of node ids for the 'animals' node type. There must be a  <nodetype_fetchNodes() function for each defined node type.  Will be called in the loadData() method of Graph.
	@return a $nodes array in which each element is an array with the property 'id' giving an id of an animal node. 
	*/
  function animals_fetchNodes() {
		global $animals;
		$graph = $this->data;
		$nodes = array();
		foreach (array_rand($animals, $graph['properties']['nodeNum']) as $index) {
			$id = 'animal_'.$index;
			$nodes[$id] = array('id'=>$id);
		}	
		return $nodes;
	}


	/**
	Will return a set of node ids for the 'foods' node type. There must be a  <nodetype_fetchNodes() function for each defined node type.  
	@return a $nodes array in which each element is an array with the property 'id' giving an id of a foods node. 
	*/
	function foods_fetchNodes() {
		global $foods;
		$graph = $this->data;
		$nodes = array();
		foreach (array_rand($foods, $graph['properties']['nodeNum']) as $index) {
			$id = 'food_'.$index;
			$nodes[$id] = array('id'=>$id);
		}
		return $nodes;
	}


	/**
		Sets the properties of each of the 'animals' nodes. The parameters will control how the nodes should be drawn by GraphViz, and how they will interact in NodeViz
		@param $nodes the array of nodes set previously
		@returns the $nodes array, with each element modified to include additional properties
	*/
	
	function animals_nodeProperties($nodes) {
		global $animals;
		foreach ($nodes as &$node) {
			$aid = str_replace('animal_', '', $node['id']);
			$node['label'] = $animals[$aid];
			$node['shape'] = 'house';
			$node['color'] = 'red';
			$node['fillcolor'] = 'pink';
			$node['value'] = rand(0, 20);
			$node['tooltip'] = $animals[$aid]." (".$node['value'].")";
		}
		$nodes = $this->scaleSizes($nodes, 'animals', 'value');
		return $nodes;	
	}

	/**
		Sets the properties of each of the 'foods' nodes. The parameters will control how the nodes should be drawn by GraphViz, and how they will interact in NodeViz
		@param $nodes the array of nodes set previously
		@returns the $nodes array, with each element modified to include additional properties
	*/
	function foods_nodeProperties($nodes) {
		global $foods;
		foreach ($nodes as &$node) {
			$fid = str_replace('food_', '', $node['id']);
			$node['label'] = $foods[$fid];
			$node['label_offset_x'] = 10;
			$node['label_offset_y'] = 5;
			$node['label_zoom_level'] = 3;
			$node['label_text_anchor'] = 'start';

			$node['shape'] = 'box';
			$node['color'] = 'black';
			$node['fillcolor'] = "#ccccff";
			$node['value'] = rand(0, 20);
			$node['tooltip'] = $foods[$fid]." (".$node['value'].")";
		}
		$nodes = $this->scaleSizes($nodes, 'foods', 'value');
		return $nodes;	
	}

	/**
		Returns an array of relationships for the 'animals_to_foods' edge type.  Each edge must have its own unique id, and must list the id of the node the edge is from ('fromId', in this case an animal node) and the node it goes to ('toId', in this case a foods node).
		@returns an array $edges in which each element is an array with elements for 'id','fromId' and 'toId'
	*/
	function animal_to_food_fetchEdges() {
		$graph = $this->data;
		$animals = $graph['nodetypesindex']['animals'];
		$foods = $graph['nodetypesindex']['foods'];
		$nodes = $graph['nodes'];
		$edges = array();

		for($x=0; $x<= $graph['properties']['edgeNum']; $x++) {
			$animal = $nodes[$animals[array_rand($animals)]];
			$food = $nodes[$foods[array_rand($foods)]];
			$id = $animal['id'].'_'.$food['id'];
			$edge = array(
				'id'=>$id,
				'fromId'=>$animal['id'],
				'toId'=>$food['id']
			);
			$edges[$id] = $edge;
		}
		return $edges;
	}

	/**
		Sets properties for each of the edges of the 'animals_to_food' type. Properties control how the edge will be rendered by GraphViz, and other control the interaction in NodeVis. 
		@param $edges an array of edges set previously
		@returns the array $edges, with each element modified to include additional parameters
	*/
	function animal_to_food_edgeProperties($edges) {
		global $animals;
		global $foods;
		foreach ($edges as &$edge) {
			$fid = str_replace('food_', '', $edge['toId']);
			$aid = str_replace('animal_', '', $edge['fromId']);
			$edge['value'] = rand(1, 100);
			$edge['weight'] = $edge['value'];
			$edge['label'] = $animals[$aid] . ' eats ' . $edge['value']. ' '. $foods[$fid];
			$edge['tooltip'] = $animals[$aid] . ' eats ' . $edge['value']. ' '. $foods[$fid];
		}
		$edges = $this->scaleSizes($edges, 'animal_to_food', 'value');
		return $edges;
	}
}

//this is the array of animals used to construct this example, not part of the graph structure unless you are building a zoo. 
$animals = array('Aardvark', 'Addax', 'Alligator', 'Alpaca', 'Anteater', 'Antelope', 'Aoudad', 'Ape', 'Argali', 'Armadillo', 'Ass', 'Baboon', 'Badger', 'Basilisk', 'Bat', 'Bear', 'Beaver', 'Bighorn', 'Bison', 'Boar', 'Budgerigar', 'Buffalo', 'Bull', 'Bunny', 'Burro', 'Camel', 'Canary', 'Capybara', 'Cat', 'Chameleon', 'Chamois', 'Cheetah', 'Chimpanzee', 'Chinchilla', 'Chipmunk', 'Civet', 'Coati', 'Colt', 'Cony', 'Cougar', 'Cow', 'Coyote', 'Crocodile', 'Crow', 'Deer', 'Dingo', 'Doe', 'Dog', 'Donkey', 'Dormouse', 'Dromedary', 'Duckbill', 'Dugong', 'Eland', 'Elephant', 'Elk', 'Ermine', 'Ewe', 'Fawn', 'Ferret', 'Finch', 'Fish', 'Fox', 'Frog', 'Gazelle', 'Gemsbok', 'Gila monster', 'Giraffe', 'Gnu', 'Goat', 'Gopher', 'Gorilla', 'Grizzly bear', 'Ground hog', 'Guanaco', 'Guinea pig', 'Hamster', 'Hare', 'Hartebeest', 'Hedgehog', 'Hippopotamus', 'Hog', 'Horse', 'Hyena', 'Ibex', 'Iguana', 'Impala', 'Jackal', 'Jaguar', 'Jerboa', 'Kangaroo', 'Kid', 'Kinkajou', 'Kitten', 'Koala', 'Koodoo', 'Lamb', 'Lemur', 'Leopard', 'Lion', 'Lizard', 'Llama', 'Lovebird', 'Lynx', 'Mandrill', 'Mare', 'Marmoset', 'Marten', 'Mink', 'Mole', 'Mongoose', 'Monkey', 'Moose', 'Mountain goat', 'Mouse', 'Mule', 'Musk deer', 'Musk-ox', 'Muskrat', 'Mustang', 'Mynah bird', 'Newt', 'Ocelot', 'Okapi', 'Opossum', 'Orangutan', 'Oryx', 'Otter', 'Ox', 'Panda', 'Panther', 'Parakeet', 'Parrot', 'Peccary', 'Pig', 'Platypus', 'Polar bear', 'Pony', 'Porcupine', 'Porpoise', 'Prairie dog', 'Pronghorn', 'Puma', 'Puppy', 'Quagga', 'Rabbit', 'Raccoon', 'Ram', 'Rat', 'Reindeer', 'Reptile', 'Rhinoceros', 'Roebuck', 'Salamander', 'Seal', 'Sheep', 'Shrew', 'Silver fox', 'Skunk', 'Sloth', 'Snake', 'Springbok', 'Squirrel', 'Stallion', 'Steer', 'Tapir', 'Tiger', 'Toad', 'Turtle', 'Vicuna', 'Walrus', 'Warthog', 'Waterbuck', 'Weasel', 'Whale', 'Wildcat', 'Wolf', 'Wolverine', 'Wombat', 'Woodchuck', 'Yak', 'Zebra', 'Zebu'); 

//this is the array of foods used to construct the example, not part of the graph structure unless you are building a garden.
$foods = array('Asparagus','Avocados','Beets','Bell peppers','Broccoli','Brussels sprouts','Cabbage','Carrots','Cauliflower','Celery','Collard greens','Cucumbers','Eggplant','Fennel','Garlic','Green beans','Green peas','Kale','Leeks','Mushrooms, crimini','Mushrooms, shiitake','Mustard greens','Olives','Onions','Potatoes','Romaine lettuce','Sea vegetables','Spinach','Squash, summer','Squash, winter','Sweet potatoes','Swiss chard','Tomatoes','Turnip greens','Yams','Apples','Apricots','Bananas','Blueberries','Cantaloupe','Cranberries','Figs','Grapefruit','Grapes','Kiwifruit','Lemon/Limes','Oranges','Papaya','Pears','Pineapple','Plums','Prunes','Raisins','Raspberries','Strawberries','Watermelon','Cheese','Eggs','Milk, cow','Milk, goat','Yogurt','Black beans','Dried peas','Garbanzo beans (chickpeas)','Kidney beans','Lentils','Lima beans','Miso','Navy beans','Pinto beans','Soybeans','Tempeh','Tofu','Almonds','Cashews','Flaxseeds','Olive oil, extra virgin','Peanuts','Pumpkin seeds','Sesame seeds','Sunflower seeds','Walnuts','Barley','Brown rice','Buckwheat','Corn','Millet','Oats','Quinoa','Rye','Spelt','Whole wheat','Basil','Black pepper','Cayenne pepper','Chili pepper, dried','Cilantro/Coriander seeds','Cinnamon, ground','Cloves','Cumin seeds','Dill','Ginger','Mustard seeds','Oregano','Parsley','Peppermint','Rosemary','Sage','Thyme','Turmeric','Blackstrap molasses','Cane juice','Honey','Maple syrup','Green tea','Soy sauce (tamari)','Water');

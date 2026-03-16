Installation (provided):
-
This is the basic setup for the a Vue3 interface backed by a Symfony REST API.

To setup your local instance, run `./setup.sh`. This command will build the local docker images, install php and npm dependencies, and start the containers.

After that, you should be able to navigate to localhost:3000 and see changes you make live. The API will be available at localhost:8000.

The Symfony based PHP code can be found in `/backend`:
- Symfony Documentation: https://symfony.com/doc/current/index.html
- An example REST Controller can be found in `backend/src/Controller/HelloWorldController.php`

The Vue3 code can be found in `/frontend`:
- Vue3 Documentation: https://vuejs.org/guide/introduction.html
- An example Component can be found in `frontend/src/components/HelloWorld.vue`
- The Component uses `axios` to call the REST Controller defined in Symfony


Backend Work:
- 
- Utilized given repo (vue3-symfony) to scaffold Docker application, running PHP on the Symfony framework

Service Overview
- Entities
    - TrainDataEntity: represents a 'train data' object that contains pertinant information about upcoming trains for a given station,
        - Fields: number of cars ($Cars), final destination for train ($Destination), and minutes until arrival ($Min) 
- Controllers
    - GetTrainDataController: controller routed to '/train-data/{station}'
    - Param: takes train station code and returns an array of upcoming trains for that station
    - Calls GetTrainDataService for return object, returns a Response with a 200 code and an array of TrainDataEntity objects upon success
- Services
    - GetTrainDataService:       
        - Service called by GetTrainDataController that queries the WMATA API to find upcoming trains for a given station, cleans and converts data into an array of TrainDataEntity objects, and returns the upcoming trains to the controller
        - Public function fetchTrainDataByStation is given a train station code, utilizes the HttpClientInterface to call the WMATA API, handles errors, and returns a cleaned array of TrainDataEntity objects to the controller
        - Private function mapToEntities takes the JSON returned by the WMATA API and converts it into an array of TrainDataEntity objects
        - Private function cleanData cleans and normalizes the data from the WMATA API before it is added to the return array in mapToEntities
        - Private function logError logs any errors that occur while calling the WMATA API
    - Expected Flow
        - A client will hit the controller endpoint with a train station code -> the service will hit the WMATA API endpoint and recieve train data based on the given code -> the data will be cleaned and parsed into an array of TrainDataEntity objects -> the array will be returned to the client
        - If there is an error, an Error will be returned to the client with the WMATA API response 
- Testing
    - Unit tests would be written to test the happy path, all errors, and edge cases 
    - Unit tests would be written to indirectly test the private functions
    - End to End tests would be written to test the WMATA API flow inside of my code
    - There is a WMATA API endpoint that tests your API key, an integration test would be written for this as well

Frontend Work:
- 
- Utilized given repo (vue3-symfony) to scaffold Docker application, using Vue

Overview
- GetTrains.vue is the component displayed on the web page. It includes a dropdown of all Red Line train stations, and when one is selected it displays a table with upcoming train information (the destination, the minutes until arrival, and the number of train cars). If there is an error, a red error message is displayed 
- Utilizes Axios to call the backend endpoint to get train data via station code, takes the return data and parses it into the displayed table
- All styling for the component can be found in settings.scss

Testing
- User testing includes clicking the drop down for all stations, and clicking the 'Error Example' option to test the error code
- Unit testing for the javascript and axios calls, utilizing mock Http to ensure the function is parsing the data in an expected way 
- Integration testing for the javascript and axious calls, ensuring the flow from the frontend to backend is working as expected
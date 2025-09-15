<?php
function sheet_updated($apiKey, $spreadsheetId, $sheetName, $cellRange, $lastUpdateFile){
// Build the Google Sheets API URL

$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$sheetName}!{$cellRange}?key={$apiKey}";
// Create context for file_get_contents with options
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 30,
        'user_agent' => 'Google Sheets API Client',
        'header' => "Accept: application/json\r\n"
    ]
]);

// Use file_get_contents instead of cURL
$response = @file_get_contents($url, false, $context);

// Check if the request failed
if ($response === false) {
    // Get more detailed error information
    $error = error_get_last();
    die("Request failed: " . ($error['message'] ?? 'Unknown error occurred'));
}


// Parse the JSON response
$data = json_decode($response, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    die("JSON Decode Error: " . json_last_error_msg());
}

// Extract and display the value from cell A1

$currentValue = isset($data['values'][0][0]) ? $data['values'][0][0] : null;

//echo ("value is :" . $currentValue);

    if ($currentValue === null) {
        return false;
    }

    // Check if the file exists
    if (!file_exists($lastUpdateFile)) {
        // File doesn't exist, create it and save the value
        file_put_contents($lastUpdateFile, $currentValue);
        return false;
    }

    // Read saved value
    $savedValue = file_get_contents($lastUpdateFile);

    // Compare values
    if ($savedValue === $currentValue) {
        return true;
    } else {

    // Values are different, update the file
    file_put_contents($lastUpdateFile, $currentValue);
    return false;
    }
}


function update_json($apiKey, $spreadsheetId, $sheetName, $outputFile) {
        // Construct API URL
        $inputJson = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/$sheetName?key=$apiKey";
        
        // Fetch and decode the data
        $response = file_get_contents($inputJson);        
        $inputData = json_decode($response, true);


        // Extract headers, display flags, and data rows
        $headers = $inputData['values'][0];
        $displays = $inputData['values'][1];
        $rows = array_slice($inputData['values'], 2);

        // Transform data to JSON array of objects
        $result = "";
        $result .="[";
        for ($i = 0; $i < count($rows); $i++) {
            if ($i > 0) $result .= ",";
            $result .= "{";
            
            $first = true;
            for ($j = 0; $j < count($headers); $j++) {
                if ($displays[$j] !== "0") {
                    if (!$first) $result .= ",";
                    $result .= '"' . $headers[$j] . '":"' . $rows[$i][$j] . '"';
                    $first = false;
                }
            }
            
            $result .= "}";
        }
        $result .= "]";

 
        // Save to file
        file_put_contents($outputFile, $result);

        return $result;
}
function jsonToGroupedData($jsonFilePath, $stu_id) {
    // Read and decode JSON file
    $jsonData = file_get_contents($jsonFilePath);
    $data = json_decode($jsonData, true);
    
    // If data is empty or invalid, return null
    if (!$data || empty($data)) {
        return null;
    }

    // Case 1: Return all students basic info (stu_id=0)
    if ($stu_id === '0') {
        $result = array();
        // Skip header (index 0) and process all student entries
        for ($i = 1; $i < count($data); $i++) {
            $result[] = array(
                'stu_id' => $data[$i]['stu_id'],
                'FullName' => $data[$i]['FullName'],
                'Syndicate' => $data[$i]['Syndicate']
            );
        }
        $finalResult = [$result]; // Wrap in array to match format
        
        // Save to stu_id.json
        file_put_contents('stu_id.json', json_encode($finalResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        return $finalResult;
    }
    
    // Case 2: Return grouped data for specific student
    // Initialize result groups
    $result = array(
        array(), // Group 1: 1-199 and non-numeric
        array(), // Group 2: 200-299
        array()  // Group 3: 300+
    );
    
    // Get labels from first element
    $labels = $data[0];
    
    // Find student data
    $studentData = null;
    foreach (array_slice($data, 1) as $row) {
        if ($row['stu_id'] === $stu_id) {
            $studentData = $row;
            break;
        }
    }
    
    // Return empty groups if student not found
    if (!$studentData) {
        return $result;
    }
    
    // Process each field
    foreach ($labels as $key => $label) {
        // Skip if no value exists
        if (!isset($studentData[$key])) {
            continue;
        }
        
        $value = $studentData[$key];
        $data_pair = array($label => $value);
        
        // Group based on key
        if (is_numeric($key)) {
            $numKey = intval($key);
            if ($numKey <= 199) {
                $result[0] = array_merge($result[0], $data_pair);
            } elseif ($numKey <= 299) {
                $result[1] = array_merge($result[1], $data_pair);
            } else {
                $result[2] = array_merge($result[2], $data_pair);
            }
        } else {
            // Non-numeric keys go to first group
            $result[0] = array_merge($result[0], $data_pair);
        }
    }
    
    // Save to {student_id}.json
    //file_put_contents($stu_id . '.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    return $result;
}



// Usage example:
header('Content-Type: application/json; charset=utf-8');
// Configuration
$apiKey = 'AIzaSyBzbPw2I0pqljbPfZERB_-peWTJNQHFR1o';
$spreadsheetId = '1C58zCYzJf7WYq_17MKzdse6MKf6rpfoZftvtlDm24CY';
$sheetName = 'student';
$lastUpdateFile = 'lastupdate.txt';
$cellRange = 'A1';
$student_id = $_GET['stu_id'];

 // Using the function with the configuration
if (sheet_updated($apiKey, $spreadsheetId, $sheetName, $cellRange, $lastUpdateFile)) {

 } else {
     $datas = update_json($apiKey, $spreadsheetId, $sheetName, 'student.json');
     jsonToGroupedData('student.json','0');
 }
 $res = jsonToGroupedData('student.json',$student_id);
 echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>

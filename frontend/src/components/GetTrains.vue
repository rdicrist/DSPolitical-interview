<template>
  <div class="page">
    <div id="wmata-logo">
      <img src="../assets/wmata-logo.png" alt="WMATA Logo" width="100">
    </div>
    <h1>WMATA Train Information</h1>

      <label for="my-dropdown">Choose a train station to view upcoming trains on the Red Line:</label>
      <select
        id="my-dropdown"
        v-model="selected"
        @change="sendSelection"
      >
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>

      <!-- For Debugging: -->
      <!-- <p>Selected: {{ selected }}</p> -->
      <!-- <p v-if="status" class="status">{{ status }}</p> -->

      <table v-if="status && !status.includes('error')">
        <thead>
          <tr>
            <th>Destination</th>
            <th>Minutes To Arrival</th>
            <th>Number of Cars</th>
          </tr>
        </thead>
        <tbody>
          <!-- If conditions for empty fields -->
          <tr v-for="(train, index) in JSON.parse(status)" :key="index">
            <td>{{ train.destination || 'Unknown' }}</td>
            <td>
              {{ (
                train.min === 'BRD' ? 'Boarding' :
                train.min === 'ARR' || train.min == 0) ? 'Arriving' :
                (train.min != null && train.min !== '' ? train.min + ' min' :
                'Unknown') }}
            </td>
            <td>{{ (train.cars != null && train.cars !== '') ? train.cars : 'Unknown' }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Generic error message for a failed request -->
      <div class="error" v-else-if="status && status.includes('error')">
        <h2>No train data available for this station. Please try again later.</h2>
      </div>

  </div>
</template>

<script setup lang="ts">
  import axios from 'axios';
  import { ref } from 'vue';

  // TODO: use the API to pull the station names / codes instead of manually entering them
const options = [
  { value: '', label: 'Please select a train station' },
  { value: 'BBB', label: 'Error Example' },
  { value: 'A15', label: 'Shady Grove' },
  { value: 'A14', label: 'Rockville' },
  { value: 'A13', label: 'Twinbrook' },
  { value: 'A12', label: 'White Flint' },
  { value: 'A11', label: 'Grosvenor' },
  { value: 'A10', label: 'Medical Center' },
  { value: 'A09', label: 'Bethesda' },
  { value: 'A08', label: 'Friendship Heights' },
  { value: 'A07', label: 'Tenleytown' },
  { value: 'A06', label: 'Van Ness UDC' },
  { value: 'A05', label: 'Cleveland Park' },
  { value: 'A04', label: 'Woodley Park Zoo' },
  { value: 'A03', label: 'Dupont Circle' },
  { value: 'A02', label: 'Farragut North' },
  { value: 'A01', label: 'Metro Center' },
  { value: 'B01', label: 'Gallery Place' },
  { value: 'B02', label: 'Judiciary Square' },
  { value: 'B03', label: 'Union Station' },
  { value: 'B35', label: 'New York Avenue' },
  { value: 'B04', label: 'Rhode Island Avenue' },
  { value: 'B05', label: 'Brookland' },
  { value: 'B06', label: 'Fort Totten' },
  { value: 'B07', label: 'Takoma' },
  { value: 'B08', label: 'Silver Spring' },
  { value: 'B09', label: 'Forest Glen' },
  { value: 'B10', label: 'Wheaton' },
  { value: 'B11', label: 'Glenmont' },
]

const selected = ref('')
const sending = ref(false)
const status = ref('')

async function sendSelection() {
  if (!selected.value) return null
  sending.value = true
  status.value = ''
  try {
    const res = await axios.post(`http://localhost:8000/train-data/${selected.value}`, {
      stationCode: selected.value,
    })
    const data = res.data

    status.value = (data ? `${typeof data === 'string' ? data : JSON.stringify(data)}` : '')
    return data
  } catch (err) {
    // axios error handling
    const e: any = err
    if (e.response) {
      status.value = `Error sending: HTTP ${e.response.status}`
    } else if (e.request) {
      status.value = 'Error sending: No response received'
    } else {
      status.value = `Error sending: ${e.message}`
    }
    return null
  } finally {
    sending.value = false
  }
}
</script>


/******************************************************************************* 

        CIRCULAR CIRX AGGREGATE MARKET DATA LIBRARY
        Multi-Exchange Data Aggregation (BitMart, XT, LBank)
        License : Open Source for private and commercial use
                     
        CIRCULAR GLOBAL LEDGERS, INC. - USA
        
                     
        Version : 2.0.0
                     
        Creation: 8/30/2024
        Updated: 8/26/2025 - Refactored to AggregateMarket
        
                  
        Originator: Gianluca De Novi, PhD 
        
*******************************************************************************/

export class AggregateMarket {
    intervalId = null
    
    // Simple cache with timestamp
    _cache = null
    _cacheTimestamp = 0
    _cacheTimeout = 30000 // 30 seconds cache
  
    /*
     * helper function use abbreviation for values K,M,B,T
     */
    numToAbbreviation(num) {
      if (num < 1000) return num.toFixed(2)
      const suffixes = ["", "K", "M", "B", "T"]
      const i = Math.floor(Math.log(num) / Math.log(1000))
      return (num / Math.pow(1000, i)).toFixed(2) + suffixes[i]
    }
  
    /*
     * fetch BitMart Exchange token and pair market data
     *
     * token: Token Symbol
     * pair: pair token symbol
     *
     * example 'BTC','USDT'
     */
  
    async getBitMartData(token, pair) {
      const URL_bitmart = `https://nag.circularlabs.io/CProxy.php?url=https://api-cloud.bitmart.com/spot/quotation/v3/ticker?symbol=${token.toUpperCase()}_${pair.toUpperCase()}`
  
      try {
        const response = await fetch(URL_bitmart)
  
        if (response.ok) {
          const data = await response.json()
          const bitmartLast = parseFloat(data.data.last)
          const bitmartFluc = parseFloat(data.data.fluctuation) * 100.0 // Convert fluctuation to percentage
          const bitmartVolC = parseFloat(data.data.v_24h)
          const bitmartVolU = parseFloat(data.data.qv_24h)
  
          // Create and return the result object
          const result = {
            lastPrice: bitmartLast.toFixed(6),
            fluctuation: bitmartFluc.toFixed(3),
            volumeToken: bitmartVolC.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }),
            volumePair: bitmartVolU.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            })
          }
  
          return result
        } else {
          console.log(`BitMart HTTP error! Status: ${response.status}`)
          return null
        }
      } catch (error) {
        console.log("Failed to fetch BitMart data:", error)
        return null
      }
    }
  
    /*
     * fetch XT Exchange token and pair market data
     *
     * token: Token Symbol
     * pair: pair token symbol
     *
     * example 'BTC','USDT'
     */
  
    async getXTData(token, pair) {
      const URL_xt = `https://nag.circularlabs.io/CProxy.php?url=https://sapi.xt.com/v4/public/ticker/24h?symbol=${token.toLowerCase()}_${pair.toLowerCase()}`
  
      try {
        const response = await fetch(URL_xt)
  
        if (response.ok) {
          const data = await response.json()
          const xtLast = parseFloat(data.result[0].c) // Assuming result is an array
          const xtFluc = parseFloat(data.result[0].cr) * 100.0 // Convert fluctuation to percentage
          const xtVolC = parseFloat(data.result[0].q)
          const xtVolU = parseFloat(data.result[0].v)
  
          // Create and return the result object
          const result = {
            lastPrice: xtLast.toFixed(6),
            fluctuation: xtFluc.toFixed(3),
            volumeToken: xtVolC.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }),
            volumePair: xtVolU.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            })
          }
  
          return result
        } else {
          console.log(`XT HTTP error! Status: ${response.status}`)
          return null
        }
      } catch (error) {
        console.log("Failed to fetch XT data:", error)
        return null
      }
    }
  
    /*
     * fetch LBank Exchange token and pair market data
     *
     * token: Token Symbol
     * pair: pair token symbol
     *
     * example 'BTC','USDT'
     */
  
    async getLBankData(token, pair) {
      const URL_lbank = `https://nag.circularlabs.io/CProxy.php?url=https://api.lbkex.com/v2/ticker.do?symbol=${token.toLowerCase()}_${pair.toLowerCase()}`
  
      try {
        const response = await fetch(URL_lbank)
  
        if (response.ok) {
          const data = await response.json()
          const lbankTicker = data.data[0].ticker
          const lbankLast = parseFloat(lbankTicker.latest)
          const lbankFluc = parseFloat(lbankTicker.change) * 100.0 // Convert fluctuation to percentage
          const lbankVolC = parseFloat(lbankTicker.vol)
          const lbankVolU = parseFloat(lbankTicker.turnover)
  
          // Create and return the result object
          const result = {
            lastPrice: lbankLast.toFixed(6),
            fluctuation: lbankFluc.toFixed(3),
            volumeToken: lbankVolC.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }),
            volumePair: lbankVolU.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            })
          }
  
          return result
        } else {
          console.log(`LBank HTTP error! Status: ${response.status}`)
          return null
        }
      } catch (error) {
        console.log("Failed to fetch LBank data:", error)
        return null
      }
    }
  
    /*
     * fetch token and pair market data from all exchanges
     *
     * token: Token Symbol
     * pair: pair token symbol
     *
     * example 'BTC','USDT'
     */
  
    async getMarketData(token, pair) {
      // Check cache first
      const now = Date.now()
      if (this._cache && (now - this._cacheTimestamp) < this._cacheTimeout) {
        console.log('🎯 Using cached market data')
        return this._cache
      }

      var URL_Circul = `https://nag.circularlabs.io/GetCirculatingSupply.php?Asset=CIRX`
      var URL_bitmart = `https://nag.circularlabs.io/CProxy.php?url=https://api-cloud.bitmart.com/spot/quotation/v3/ticker?symbol=${token.toUpperCase()}_${pair.toUpperCase()}`
      var URL_xt = `https://nag.circularlabs.io/CProxy.php?url=https://sapi.xt.com/v4/public/ticker/24h?symbol=${token.toLowerCase()}_${pair.toLowerCase()}`
      var URL_lbank = `https://nag.circularlabs.io/CProxy.php?url=https://api.lbkex.com/v2/ticker.do?symbol=${token.toLowerCase()}_${pair.toLowerCase()}`
  
      // Initialize variables to store the cumulative data
      let totalLast = 0
      let totalFluc = 0
      let totalVolC = 0
      let totalVolU = 0
      let count = 0
      let circSupply = 0
  
      try {
        // Create timeout promises for each API call
        const timeoutMs = 5000 // 5 second timeout instead of default
        
        const fetchWithTimeout = (url, timeout = timeoutMs) => {
          return Promise.race([
            fetch(url),
            new Promise((_, reject) => 
              setTimeout(() => reject(new Error(`Timeout: ${url}`)), timeout)
            )
          ])
        }
        
        // Fetch data from all APIs concurrently with timeouts
        const [circulatingSupply, bitmartResponse, xtResponse, lbankResponse] =
          await Promise.allSettled([
            fetchWithTimeout(URL_Circul),
            fetchWithTimeout(URL_bitmart),
            fetchWithTimeout(URL_xt),
            fetchWithTimeout(URL_lbank)
          ])
  
        // Handle Circular response
        if (circulatingSupply.status === 'fulfilled' && circulatingSupply.value.ok) {
          const CircularData = await circulatingSupply.value.json()
          circSupply = parseInt(CircularData.circulatingSupply)
        } else {
          console.log(`Circulating Supply Error:`, circulatingSupply.reason?.message || 'Failed')
        }
  
        // Handle BitMart response
        if (bitmartResponse.status === 'fulfilled' && bitmartResponse.value.ok) {
          const bitmartData = await bitmartResponse.value.json()
          if (bitmartData.data) {
            const bitmartLast = parseFloat(bitmartData.data.last)
            const bitmartFluc = parseFloat(bitmartData.data.fluctuation) * 100.0 // Convert fluctuation to percentage
            const bitmartVolC = parseFloat(bitmartData.data.v_24h)
            const bitmartVolU = parseFloat(bitmartData.data.qv_24h)
  
            totalLast += bitmartLast || 0
            totalFluc += bitmartFluc || 0
            totalVolC += bitmartVolC || 0
            totalVolU += bitmartVolU || 0
            count++
            /**/
          } else {
            console.log(
              `Unexpected BitMart data format: ${JSON.stringify(bitmartData)}`
            )
          }
        } else {
          console.log(`BitMart Error:`, bitmartResponse.reason?.message || 'Failed to fetch')
        }
  
        // Handle XT response
        if (xtResponse.status === 'fulfilled' && xtResponse.value.ok) {
          const xtData = await xtResponse.value.json()
          if (xtData.result && xtData.result[0]) {
            const xtLast = parseFloat(xtData.result[0].c) // Assuming result is an array
            const xtFluc = parseFloat(xtData.result[0].cr) * 100.0 // Convert fluctuation to percentage
            const xtVolC = parseFloat(xtData.result[0].q)
            const xtVolU = parseFloat(xtData.result[0].v)
  
            totalLast += xtLast || 0
            totalFluc += xtFluc || 0
            totalVolC += xtVolC || 0
            totalVolU += xtVolU || 0
            count++
            /**/
          } else {
            console.log(`Unexpected XT data format: ${JSON.stringify(xtData)}`)
          }
        } else {
          console.log(`XT Error:`, xtResponse.reason?.message || 'Failed to fetch')
        }
  
        // Handle LBank response
        if (lbankResponse.status === 'fulfilled' && lbankResponse.value.ok) {
          const lbankData = await lbankResponse.value.json()
          if (lbankData.data && lbankData.data[0] && lbankData.data[0].ticker) {
            const lbankTicker = lbankData.data[0].ticker
            const lbankLast = parseFloat(lbankTicker.latest)
            const lbankFluc = parseFloat(lbankTicker.change) * 100.0 // Convert fluctuation to percentage
            const lbankVolC = parseFloat(lbankTicker.vol)
            const lbankVolU = parseFloat(lbankTicker.turnover)
  
            totalLast += lbankLast || 0
            totalFluc += lbankFluc / 100 || 0
            totalVolC += lbankVolC || 0
            totalVolU += lbankVolU || 0
            count++
          } else {
            console.log(
              `Unexpected LBank data format: ${JSON.stringify(lbankData)}`
            )
          }
        } else {
          console.log(`LBank Error:`, lbankResponse.reason?.message || 'Failed to fetch')
        }
  
        // Ensure at least one valid response was processed
        if (count === 0) {
          throw new Error("No valid data fetched from any of the exchanges.")
        }
  
        // Calculate averages
        const averageLast = (totalLast / count).toFixed(6)
        const averageFluc = (totalFluc / count).toFixed(3)
  
        // Sum of volumes
        const totalFormattedVolC = totalVolC.toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        })
        const totalFormattedVolU = totalVolU.toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        })
  
        // Create the result object
        const result = {
          circulatingSupply: circSupply,
          averagePrice: averageLast,
          averageFluctuation: averageFluc,
          totalVolumeCIRX: totalFormattedVolC,
          totalVolumeUSDT: totalFormattedVolU
        }
  
        // Cache the result
        this._cache = result
        this._cacheTimestamp = Date.now()
        console.log('💾 Cached fresh market data')
  
        return result
      } catch (error) {
        console.log("Failed to fetch the market data:", error)
        return null
      }
    }
  
    /*
     * Periodically fetch market data at a given interval
     *
     * token: Token Symbol
     * pair: pair token symbol
     * interval: Interval in milliseconds
     * callback: Function to call with the fetched data
     *
     * example:  Start fetching market data every 5 seconds (5000 milliseconds)
     *
     * CMarket.fetchMarketDataPeriodically('BTC', 'USDT', 5000, handleMarketData);
     */
  
    StartFetching(token, pair, interval, callback) {
      // Start the interval
      this.intervalId = setInterval(async () => {
        try {
          // Fetch the market data
          const result = await this.getMarketData(token, pair)
          // Call the provided callback with the result
          callback(result)
        } catch (error) {
          console.log("Error fetching market data:", error)
        }
      }, interval)
    }
  
    /*
     * Stop the periodic fetching of market data
     *
     * example: Stop fetching after 20 seconds
     *
     * setTimeout(() => {CMarket.stopFetchingMarketData();}, 20000);
     */
    stopFetching() {
      if (this.intervalId !== null) {
        clearInterval(this.intervalId)
        this.intervalId = null
        console.log("Stopped fetching market data.")
      } else {
        console.log("No ongoing market data fetching to stop.")
      }
    }
  }
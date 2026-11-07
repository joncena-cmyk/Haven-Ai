#pragma once

#include <Arduino.h>
#include <Wire.h>
#include <SPI.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>

// ============================================================================
// HAVEN AI — Swappable 3-Axis Accelerometer Driver Layer (Phase 3)
// Abstracts raw accelerometer configuration and burst reads so MPU6050 (I2C)
// can be swapped for ADXL345 (SPI) or LIS3DH (SPI) without modifying the
// high-rate LOGGER loop or the Live-Check loop.
// ============================================================================

class IAccelDriver {
public:
  virtual ~IAccelDriver() {}

  // Initialize sensor hardware. When highRateLoggerMode == true, configures
  // the widest available digital low-pass filter (no 21 Hz cutoff) and highest
  // bus clock to support >= 1000 Hz sampling.
  virtual bool begin(bool highRateLoggerMode) = 0;

  // Read a single 3-axis acceleration sample in units of g (1g = 9.80665 m/s^2).
  virtual bool readSampleG(float &ax, float &ay, float &az) = 0;

  // Lightweight bus check (I2C ACK or SPI WHO_AM_I / DEVID) to detect live connect/disconnect.
  virtual bool isConnected() = 0;

  // Metadata reported in the LOGGER CSV header.
  virtual const char* sensorName() const = 0;
  virtual int rangeG() const = 0;
  virtual int bandwidthHz() const = 0;
};

// ────────────────────────────────────────────────────────────────────────────
// 1. MPU6050 I2C Driver (Default Hardware)
//    - Uses 400 kHz Fast-Mode I2C and direct 6-byte register burst reads
//      (0x3B..0x40) to sustain 1000 Hz sampling.
//    - Sets DLPF_CFG = 0 (MPU6050_BAND_260_HZ, widest accelerometer bandwidth)
//      and SMPLRT_DIV = 0 instead of the 21 Hz low-pass filter.
// ────────────────────────────────────────────────────────────────────────────
class Mpu6050I2cDriver : public IAccelDriver {
public:
  Mpu6050I2cDriver(int sdaPin, int sclPin, uint8_t i2cAddr = 0x68)
      : _sda(sdaPin), _scl(sclPin), _addr(i2cAddr), _ready(false), _bwHz(260) {}

  bool begin(bool highRateLoggerMode) override {
    Wire.begin(_sda, _scl);
    Wire.setClock(highRateLoggerMode ? 400000UL : 400000UL);

    if (!probe()) {
      _ready = false;
      return false;
    }

    if (!_mpu.begin(_addr, &Wire)) {
      _ready = false;
      return false;
    }

    // Configure ±8g full-scale range (4096 LSB/g)
    _mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
    _mpu.setGyroRange(MPU6050_RANGE_500_DEG);

    // Disable the restrictive 21 Hz low-pass filter; use widest DLPF (260 Hz)
    _mpu.setFilterBandwidth(MPU6050_BAND_260_HZ);
    _bwHz = 260;

    // Explicitly write SMPLRT_DIV (0x19) = 0x00 and CONFIG (0x1A) = 0x00 (260 Hz DLPF)
    writeRegister(0x19, 0x00);
    writeRegister(0x1A, 0x00);

    _ready = true;
    return true;
  }

  bool readSampleG(float &ax, float &ay, float &az) override {
    if (!_ready) return false;

    // Fast 6-byte burst read from ACCEL_XOUT_H (0x3B)
    Wire.beginTransmission(_addr);
    Wire.write(0x3B);
    if (Wire.endTransmission(false) != 0) {
      _ready = false;
      return false;
    }

    uint8_t bytesReceived = Wire.requestFrom(_addr, (uint8_t)6, (uint8_t)true);
    if (bytesReceived < 6) {
      return false;
    }

    int16_t rawX = (int16_t)((Wire.read() << 8) | Wire.read());
    int16_t rawY = (int16_t)((Wire.read() << 8) | Wire.read());
    int16_t rawZ = (int16_t)((Wire.read() << 8) | Wire.read());

    // ±8g scale factor = 4096.0 LSB/g
    constexpr float kScale8G = 1.0f / 4096.0f;
    ax = (float)rawX * kScale8G;
    ay = (float)rawY * kScale8G;
    az = (float)rawZ * kScale8G;
    return true;
  }

  bool probe() const {
    Wire.beginTransmission(_addr);
    return (Wire.endTransmission() == 0);
  }

  bool isConnected() override {
    return probe();
  }

  const char* sensorName() const override { return "MPU6050"; }
  int rangeG() const override { return 8; }
  int bandwidthHz() const override { return _bwHz; }

private:
  void writeRegister(uint8_t reg, uint8_t val) {
    Wire.beginTransmission(_addr);
    Wire.write(reg);
    Wire.write(val);
    Wire.endTransmission(true);
  }

  int _sda;
  int _scl;
  uint8_t _addr;
  bool _ready;
  int _bwHz;
  Adafruit_MPU6050 _mpu;
};

// ────────────────────────────────────────────────────────────────────────────
// 2. ADXL345 4-Wire SPI Driver (Drop-in replacement for high-bandwidth SPI)
//    - Supports 1600 Hz / 3200 Hz ODR over 5 MHz SPI (BW_RATE 0x2C = 0x0E/0x0F).
// ────────────────────────────────────────────────────────────────────────────
class Adxl345SpiDriver : public IAccelDriver {
public:
  explicit Adxl345SpiDriver(int csPin) : _cs(csPin), _ready(false) {}

  bool begin(bool highRateLoggerMode) override {
    pinMode(_cs, OUTPUT);
    digitalWrite(_cs, HIGH);
    SPI.begin();

    uint8_t devId = readReg(0x00); // DEVID register == 0xE5
    if (devId != 0xE5) {
      _ready = false;
      return false;
    }

    // DATA_FORMAT (0x31): FULL_RES (bit 3) | ±16g (0x03) -> 0x0B (3.9 mg/LSB)
    writeReg(0x31, 0x0B);
    // BW_RATE (0x2C): 0x0E = 1600 Hz ODR (800 Hz BW), 0x0F = 3200 Hz ODR (1600 Hz BW)
    writeReg(0x2C, highRateLoggerMode ? 0x0E : 0x0A);
    // POWER_CTL (0x2D): Measure bit (0x08)
    writeReg(0x2D, 0x08);

    _ready = true;
    return true;
  }

  bool readSampleG(float &ax, float &ay, float &az) override {
    if (!_ready) return false;
    SPI.beginTransaction(SPISettings(5000000, MSBFIRST, SPI_MODE3));
    digitalWrite(_cs, LOW);
    SPI.transfer(0x80 | 0x40 | 0x32); // Multi-byte read from DATAX0 (0x32)
    int16_t rawX = (int16_t)(SPI.transfer(0x00) | (SPI.transfer(0x00) << 8));
    int16_t rawY = (int16_t)(SPI.transfer(0x00) | (SPI.transfer(0x00) << 8));
    int16_t rawZ = (int16_t)(SPI.transfer(0x00) | (SPI.transfer(0x00) << 8));
    digitalWrite(_cs, HIGH);
    SPI.endTransaction();

    constexpr float kAdxlFullResScale = 0.00390625f; // 1/256 g/LSB in FULL_RES mode
    ax = (float)rawX * kAdxlFullResScale;
    ay = (float)rawY * kAdxlFullResScale;
    az = (float)rawZ * kAdxlFullResScale;
    return true;
  }

  bool isConnected() override {
    return (readReg(0x00) == 0xE5);
  }

  const char* sensorName() const override { return "ADXL345_SPI"; }
  int rangeG() const override { return 16; }
  int bandwidthHz() const override { return 800; }

private:
  uint8_t readReg(uint8_t reg) {
    SPI.beginTransaction(SPISettings(5000000, MSBFIRST, SPI_MODE3));
    digitalWrite(_cs, LOW);
    SPI.transfer(0x80 | (reg & 0x3F));
    uint8_t val = SPI.transfer(0x00);
    digitalWrite(_cs, HIGH);
    SPI.endTransaction();
    return val;
  }

  void writeReg(uint8_t reg, uint8_t val) {
    SPI.beginTransaction(SPISettings(5000000, MSBFIRST, SPI_MODE3));
    digitalWrite(_cs, LOW);
    SPI.transfer(reg & 0x3F);
    SPI.transfer(val);
    digitalWrite(_cs, HIGH);
    SPI.endTransaction();
  }

  int _cs;
  bool _ready;
};

